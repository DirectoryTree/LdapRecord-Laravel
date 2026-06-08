<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Import\Hydrators\PasswordHydrator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class PasswordHydratorTest extends TestCase
{
    public function test_password_hydrator_uses_random_password()
    {
        $entry = new Entry;
        $model = new PasswordHydratorTestModelStub;
        $hydrator = new PasswordHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
    }

    public function test_password_hydrator_does_nothing_when_password_column_is_disabled()
    {
        $entry = new Entry;
        $model = new PasswordHydratorTestModelStub;
        $hydrator = new PasswordHydrator(['password_column' => false]);

        $hydrator->hydrate($entry, $model);

        $this->assertNull($model->password);
    }

    public function test_password_hydrator_uses_given_password_when_password_sync_is_enabled()
    {
        $entry = new Entry;
        $model = new PasswordHydratorTestModelStub;
        $hydrator = new PasswordHydrator(['sync_passwords' => true], ['password' => 'secret']);

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
        $this->assertTrue(Hash::check('secret', $model->password));
    }

    public function test_password_hydrator_ignores_password_when_password_sync_is_disabled()
    {
        $entry = new Entry;
        $model = new PasswordHydratorTestModelStub;
        $hydrator = new PasswordHydrator(['sync_passwords' => false], ['password' => 'secret']);

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
        $this->assertFalse(Hash::check('secret', $model->password));
    }

    public function test_password_hydrator_uses_models_get_auth_password_name_if_available()
    {
        $entry = new Entry;
        $model = new PasswordHydratorTestModelWithCustomPasswordStub;
        $hydrator = new PasswordHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->custom_password));
    }

    public function test_password_hydrator_uses_model_attribute_mutator_if_available()
    {
        $entry = new Entry;
        $model = new PasswordHydratorTestModelWithPasswordAttributeStub;
        $hydrator = new PasswordHydrator(['sync_passwords' => true], ['password' => 'secret']);

        $hydrator->hydrate($entry, $model);

        $this->assertFalse(Hash::needsRehash($model->password));
        $this->assertTrue(Hash::check('secret', $model->password));
    }
}

class PasswordHydratorTestModelStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;
}

class PasswordHydratorTestModelWithCustomPasswordStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;

    public function getAuthPasswordName()
    {
        return 'custom_password';
    }
}

class PasswordHydratorTestModelWithPasswordAttributeStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;

    protected function password(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => Hash::make($value),
        );
    }
}
