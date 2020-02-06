<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Laravel\Testing\FakeSearchableDirectory;

class LiveAuthenticationTest extends TestCase
{
    public function test_database_sync_authentication()
    {
        $fake = FakeSearchableDirectory::setup();

        $this->setupDatabaseUserProvider();

        $user = User::create(['cn' => 'John', 'mail' => 'jdoe@email.com']);

        $fake->actingAs($user);

        $this->assertTrue(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));

        $model = Auth::user();

        $this->assertInstanceOf(TestUser::class, $model);
        $this->assertEquals('jdoe@email.com', $model->email);
        $this->assertEquals('John', $model->name);
    }

    public function test_plain_ldap_authentication()
    {
        $fake = FakeSearchableDirectory::setup();

        $this->setupPlainUserProvider();

        $user = User::create(['cn' => 'John', 'mail' => 'jdoe@email.com']);

        $fake->actingAs($user);

        $this->assertTrue(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));

        $model = Auth::user();

        $this->assertInstanceOf(User::class, $model);
        $this->assertTrue($user->is($model));
        $this->assertEquals($user->mail[0], $model->mail[0]);
        $this->assertEquals($user->getDn(), $model->getDn());
    }
}
