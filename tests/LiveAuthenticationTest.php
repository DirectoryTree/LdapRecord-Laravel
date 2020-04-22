<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\User;

class LiveAuthenticationTest extends TestCase
{
    use WithFaker;

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

        $fake = DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
            'objectguid' => $this->faker->uuid,
        ]);

        $fake->actingAs($user);

        $this->assertTrue(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));

        $model = Auth::user();

        $this->assertInstanceOf(TestUserModelStub::class, $model);
        $this->assertEquals($user->cn[0], $model->name);
        $this->assertEquals($user->mail[0], $model->email);
        $this->assertEquals($user->getConvertedGuid(), $model->guid);
        $this->assertFalse(Auth::attempt(['mail' => 'invalid', 'password' => 'secret']));
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

        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
            'objectguid' => $this->faker->uuid,
        ]);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }

    public function test_database_sync_fails_without_object_guid()
    {
        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $user = User::create(['cn' => 'John', 'mail' => 'jdoe@email.com']);

        $this->expectException(LdapRecordException::class);

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

        $fake = DirectoryEmulator::setup();

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

        DirectoryEmulator::setup();

        $this->setupPlainUserProvider();

        $user = User::create(['cn' => 'John', 'mail' => 'jdoe@email.com']);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }
}
