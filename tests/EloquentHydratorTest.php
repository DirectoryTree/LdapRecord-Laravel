<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\EloquentHydrator;
use LdapRecord\Laravel\Hydrators\AttributeHydrator;
use LdapRecord\Laravel\Hydrators\DomainHydrator;
use LdapRecord\Laravel\Hydrators\GuidHydrator;
use LdapRecord\Laravel\Hydrators\PasswordHydrator;
use LdapRecord\Models\Entry;

class EloquentHydratorTest extends TestCase
{
    public function test_guid_hydrator()
    {
        $entry = new Entry(['objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2']);
        $model = new TestUserModelStub;
        $hydrator = new GuidHydrator();

        $hydrator->hydrate($entry, $model);

        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
    }

    public function test_domain_hydrator_uses_default_connection_name()
    {
        $entry = new Entry;
        $model = new TestUserModelStub;
        $hydrator = new DomainHydrator();

        $hydrator->hydrate($entry, $model);

        $this->assertEquals('default', $model->domain);
    }

    public function test_attribute_hydrator()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestUserModelStub;
        AttributeHydrator::with(['sync_attributes' => ['foo' => 'bar']])
            ->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_password_hydrator_uses_random_password()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestUserModelStub;
        $hydrator = new PasswordHydrator();

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
    }

    public function test_password_hydrator_does_nothing_when_password_column_is_disabled()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestUserModelStub;
        $hydrator = new PasswordHydrator(['password_column' => false]);

        $hydrator->hydrate($entry, $model);

        $this->assertNull($model->password);
    }

    public function test_password_hydrator_uses_given_password_when_password_sync_is_enabled()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestUserModelStub;
        $hydrator = new PasswordHydrator(['sync_passwords' => true], ['password' => 'secret']);

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
        $this->assertTrue(Hash::check('secret', $model->password));
    }

    public function test_password_hydrator_ignores_password_when_password_sync_is_disabled()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestUserModelStub;
        $hydrator = new PasswordHydrator(['sync_passwords' => false], ['password' => 'secret']);

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
        $this->assertFalse(Hash::check('secret', $model->password));
    }

    public function test_hydrator_uses_all_hydrators()
    {
        $entry = new Entry([
            'bar' => 'baz',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);
        $model = new TestUserModelStub;

        $hydrator = new EloquentHydrator(['sync_attributes' => ['foo' => 'bar']]);

        $hydrator->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
        $this->assertEquals('default', $model->domain);
        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
    }
}
