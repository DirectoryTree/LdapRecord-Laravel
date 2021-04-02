<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Laravel\Tests\Feature\TestUserModelStub;
use LdapRecord\Models\ActiveDirectory\User;
use Ramsey\Uuid\Uuid;

class EmulatedModelBindingTest extends DatabaseTestCase
{
    public function test_ldap_users_are_bound_to_models_using_trait()
    {
        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        DirectoryEmulator::setup();

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@federalbridge.ca',
            'objectguid' => Uuid::uuid4()->toString(),
        ]);

        $model = TestUserModelStub::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'guid' => $user->objectguid[0],
            'password' => 'secret',
        ]);

        Auth::login($model);

        $this->assertInstanceOf(User::class, $model->ldap);
        $this->assertTrue($user->is($model->ldap));
        $this->assertTrue(isset($model->ldap));
    }

    public function test_ldap_users_are_not_bound_when_model_cannot_be_located()
    {
        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        DirectoryEmulator::setup();

        $model = TestUserModelStub::create([
            'name' => 'John',
            'email' => 'jdoe@email.com',
            'guid' => Uuid::uuid4()->toString(),
            'password' => 'secret',
        ]);

        Auth::login($model);

        $this->assertNull($model->ldap);
    }
}
