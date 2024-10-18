<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Import\EloquentHydrator;
use LdapRecord\Laravel\Import\Hydrators\AttributeHydrator;
use LdapRecord\Laravel\Import\Hydrators\DomainHydrator;
use LdapRecord\Laravel\Import\Hydrators\GuidHydrator;
use LdapRecord\Laravel\Import\Hydrators\PasswordHydrator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class EloquentHydratorTest extends TestCase
{
    public function test_guid_hydrator()
    {
        $entry = new Entry(['objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2']);
        $model = new TestModelStub;
        $hydrator = new GuidHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
    }

    public function test_domain_hydrator_uses_default_connection_name()
    {
        $entry = new Entry;
        $model = new TestModelStub;
        $hydrator = new DomainHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertEquals('default', $model->domain);
    }

    public function test_attribute_hydrator()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestModelStub;

        AttributeHydrator::with([
            'sync_attributes' => ['foo' => 'bar'],
        ])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_attribute_hydrator_can_use_handle_function_of_class()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestModelStub;

        AttributeHydrator::with([
            'sync_attributes' => [TestAttributeHandlerHandleStub::class],
        ])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_attribute_hydrator_can_use_invokable_class()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestModelStub;

        AttributeHydrator::with(['sync_attributes' => [
            TestAttributeHandlerInvokableStub::class,
        ]])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_attribute_hydrator_can_use_inline_function()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestModelStub;

        AttributeHydrator::with(['sync_attributes' => [
            function ($object, $eloquent) {
                $eloquent->foo = $object->getFirstAttribute('bar');
            },
        ]])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_password_hydrator_uses_random_password()
    {
        $entry = new Entry;
        $model = new TestModelStub;
        $hydrator = new PasswordHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
    }

    public function test_password_hydrator_does_nothing_when_password_column_is_disabled()
    {
        $entry = new Entry;
        $model = new TestModelStub;
        $hydrator = new PasswordHydrator(['password_column' => false]);

        $hydrator->hydrate($entry, $model);

        $this->assertNull($model->password);
    }

    public function test_password_hydrator_uses_given_password_when_password_sync_is_enabled()
    {
        $entry = new Entry;
        $model = new TestModelStub;
        $hydrator = new PasswordHydrator(['sync_passwords' => true], ['password' => 'secret']);

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
        $this->assertTrue(Hash::check('secret', $model->password));
    }

    public function test_password_hydrator_ignores_password_when_password_sync_is_disabled()
    {
        $entry = new Entry;
        $model = new TestModelStub;
        $hydrator = new PasswordHydrator(['sync_passwords' => false], ['password' => 'secret']);

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
        $this->assertFalse(Hash::check('secret', $model->password));
    }

    public function test_password_hydrator_uses_models_get_auth_password_name_if_available()
    {
        $entry = new Entry;
        $model = new TestModelWithCustomPasswordStub;
        $hydrator = new PasswordHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->custom_password));
    }

    public function test_hydrator_uses_all_hydrators()
    {
        $entry = new Entry([
            'bar' => 'baz',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $model = new TestModelStub;

        (new EloquentHydrator(['sync_attributes' => ['foo' => 'bar']]))
            ->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
        $this->assertEquals('default', $model->domain);
        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
    }
}

class TestModelStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;
}

class TestModelWithCustomPasswordStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;

    public function getAuthPasswordName()
    {
        return 'custom_password';
    }
}

class TestAttributeHandlerHandleStub
{
    public function handle($object, $eloquent)
    {
        $eloquent->foo = $object->getFirstAttribute('bar');
    }
}

class TestAttributeHandlerInvokableStub
{
    public function __invoke($object, $eloquent)
    {
        $eloquent->foo = $object->getFirstAttribute('bar');
    }
}
