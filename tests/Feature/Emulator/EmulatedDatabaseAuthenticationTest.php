<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\HasLdapUser;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\Completed;
use LdapRecord\Laravel\Events\Auth\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\Import\ImportException;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

class EmulatedDatabaseAuthenticationTest extends DatabaseTestCase
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

        $this->setupDatabaseUserProvider(['database' => [
            'model' => TestEmulatorUserModelStub::class,
            'sync_passwords' => true,
            'sync_attributes' => [
                'name' => 'cn',
                'email' => 'mail',
            ],
        ]]);

        $user = LdapUser::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
            'objectguid' => $this->faker->uuid,
        ]);

        $fake->actingAs($user);

        $this->assertTrue(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));

        $model = Auth::user();

        $this->assertInstanceOf(TestEmulatorUserModelStub::class, $model);
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

        $this->setupDatabaseUserProvider(['database' => [
            'model' => TestEmulatorUserModelStub::class,
        ]]);

        $user = LdapUser::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
        ]);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }

    public function test_database_sync_fails_without_object_guid()
    {
        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider(['database' => [
            'model' => TestEmulatorUserModelStub::class,
        ]]);

        $user = LdapUser::create([
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

        $this->setupDatabaseUserProvider(['database' => [
            'model' => TestEmulatorUserModelStub::class,
        ]]);

        $guard = Auth::guard();

        $databaseUserProvider = $guard->getProvider();

        $databaseUserProvider->resolveUsersUsing(function () {
            throw new \Exception;
        });

        $user = TestEmulatorUserModelStub::create([
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

        $this->setupDatabaseUserProvider(['database' => [
            'model' => TestEmulatorUserModelStub::class,
        ]]);

        $guard = Auth::guard();

        $databaseUserProvider = $guard->getProvider();

        $databaseUserProvider->resolveUsersUsing(function () {
            // Do nothing.
        });

        $user = TestEmulatorUserModelStub::create([
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

        $this->setupDatabaseUserProvider(['database' => [
            'model' => TestEmulatorUserModelStub::class,
        ]]);

        $guard = Auth::guard();

        $databaseUserProvider = $guard->getProvider();

        $databaseUserProvider->resolveUsersUsing(function () {
            // Do nothing.
        });

        $user = TestEmulatorUserModelStub::create([
            'name' => 'John Doe',
            'email' => 'jdoe@email.com',
            'password' => Hash::make('secret'),
        ]);

        $this->assertFalse($guard->attempt(['mail' => $user->email, 'password' => 'secret']));
    }
}

class TestEmulatorUserModelStub extends User implements LdapAuthenticatable
{
    use SoftDeletes, AuthenticatesWithLdap, HasLdapUser;

    protected $guarded = [];

    protected $table = 'users';
}
