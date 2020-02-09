<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User;
use Ramsey\Uuid\Uuid;

class BindLdapUserTest extends TestCase
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

        $model = TestUser::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'guid' => $user->objectguid[0],
            'password' => 'secret',
        ]);

        Auth::login($model);

        $this->assertInstanceOf(User::class, $model->ldap);
        $this->assertTrue($user->is($model->ldap));
    }
}
