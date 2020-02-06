<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Laravel\Testing\FakeSearchableDirectory;

class LiveAuthenticationTest extends TestCase
{
    public function test_database_sync_authentication_passes()
    {
        $this->expectsEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
            Authenticating::class,
            Authenticated::class,
            DiscoveredWithCredentials::class,
        ]);

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

    public function test_database_sync_authentication_fails()
    {
        $this->expectsEvents([
            Authenticating::class,
            Importing::class,
            Synchronizing::class,
            Synchronized::class,
            DiscoveredWithCredentials::class,
        ])->doesntExpectEvents([
            Authenticated::class,
            Imported::class,
        ]);

        FakeSearchableDirectory::setup();

        $this->setupDatabaseUserProvider();

        $user = User::create(['cn' => 'John', 'mail' => 'jdoe@email.com']);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }

    public function test_plain_ldap_authentication_passes()
    {
        $this->expectsEvents([
            Authenticating::class,
            Authenticated::class,
            DiscoveredWithCredentials::class,
        ])->doesntExpectEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

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

    public function test_plain_ldap_authentication_fails()
    {
        $this->expectsEvents([
            Authenticating::class,
            DiscoveredWithCredentials::class,
        ])->doesntExpectEvents([
            Authenticated::class,
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

        FakeSearchableDirectory::setup();

        $this->setupPlainUserProvider();

        $user = User::create(['cn' => 'John', 'mail' => 'jdoe@email.com']);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }
}
