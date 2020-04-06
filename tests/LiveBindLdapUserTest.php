<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User;
use Ramsey\Uuid\Uuid;

class LiveBindLdapUserTest extends TestCase
{
    public function test_ldap_users_are_bound_to_models_using_trait()
    {
        $this->setupDatabaseUserProvider();

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
    }

    public function test_ldap_users_are_not_bound_when_model_cannot_be_located()
    {
        $this->setupDatabaseUserProvider();

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
