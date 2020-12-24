<?php

namespace LdapRecord\Laravel\Tests\Emulator;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Auth\Completed;
use LdapRecord\Laravel\Events\Auth\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\Import\ImportException;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\DatabaseProviderTestCase;
use LdapRecord\Laravel\Tests\TestUserModelStub;
use LdapRecord\Models\ActiveDirectory\User;

class EmulatedAuthenticationTest extends DatabaseProviderTestCase
{
    use WithFaker;

    public function test_database_sync_authentication_passes()
    {
        $this->expectsEvents([
            DiscoveredWithCredentials::class,
            Importing::class,
            Imported::class,
            Saved::class,
            Synchronizing::class,
            Synchronized::class,
            Binding::class,
            Bound::class,
            Completed::class,
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
            Binding::class,
            Importing::class,
            Synchronizing::class,
            Synchronized::class,
            DiscoveredWithCredentials::class,
        ])->doesntExpectEvents([
            Bound::class,
            Imported::class,
        ]);

        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
        ]);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }

    public function test_database_sync_fails_without_object_guid()
    {
        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
            'objectguid' => '',
        ]);

        $this->expectException(ImportException::class);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }

    public function test_database_authentication_fallback_works_when_enabled_and_exception_is_thrown()
    {
        $this->doesntExpectEvents([
            Binding::class,
            Bound::class,
            DiscoveredWithCredentials::class,
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $guard = Auth::guard();

        $databaseUserProvider = $guard->getProvider();

        $databaseUserProvider->resolveUsersUsing(function () {
            throw new \Exception;
        });

        $user = TestUserModelStub::create([
            'name' => 'John Doe',
            'email' => 'jdoe@email.com',
            'password' => Hash::make('secret'),
        ]);

        $this->assertTrue($guard->attempt([
            'mail' => $user->email,
            'password' => 'secret',
            'fallback' => [
                'email' => $user->email,
                'password' => 'secret',
            ],
        ]));
    }

    public function test_database_authentication_fallback_works_when_enabled_and_null_is_returned()
    {
        $this->doesntExpectEvents([
            Binding::class,
            Bound::class,
            DiscoveredWithCredentials::class,
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $guard = Auth::guard();

        $databaseUserProvider = $guard->getProvider();

        $databaseUserProvider->resolveUsersUsing(function () {
        });

        $user = TestUserModelStub::create([
            'name' => 'John Doe',
            'email' => 'jdoe@email.com',
            'password' => Hash::make('secret'),
        ]);

        $this->assertTrue($guard->attempt([
            'mail' => $user->email,
            'password' => 'secret',
            'fallback' => [
                'email' => $user->email,
                'password' => 'secret',
            ],
        ]));
    }

    public function test_database_authentication_fallback_is_not_performed_when_fallback_credentials_are_not_defined()
    {
        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider();

        $guard = Auth::guard();

        $databaseUserProvider = $guard->getProvider();

        $databaseUserProvider->resolveUsersUsing(function () {
        });

        $user = TestUserModelStub::create([
            'name' => 'John Doe',
            'email' => 'jdoe@email.com',
            'password' => Hash::make('secret'),
        ]);

        $this->assertFalse($guard->attempt(['mail' => $user->email, 'password' => 'secret']));
    }

    public function test_plain_ldap_authentication_passes()
    {
        $this->expectsEvents([
            Binding::class,
            Bound::class,
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
            Binding::class,
            DiscoveredWithCredentials::class,
        ])->doesntExpectEvents([
            Bound::class,
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
