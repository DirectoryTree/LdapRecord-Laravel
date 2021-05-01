<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\CompletedWithWindows;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\Middleware\WindowsAuthenticate;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Laravel\Tests\Feature\TestUserModelStub;
use LdapRecord\Models\ActiveDirectory\User;

class EmulatedWindowsAuthenticateTest extends DatabaseTestCase
{
    use WithFaker;

    public function test_windows_authenticated_user_is_signed_in()
    {
        $this->expectsEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
            Binding::class,
            Bound::class,
            Saved::class,
            CompletedWithWindows::class,
        ]);

        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
            'samaccountname' => 'jdoe',
            'objectguid' => $this->faker->uuid,
        ]);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'LOCAL\\jdoe');
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $this->assertTrue(auth()->check());
            $this->assertEquals($user->getConvertedGuid(), auth()->user()->guid);
        });
    }

    public function test_kerberos_authenticated_user_is_signed_in()
    {
        $this->expectsEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
            Binding::class,
            Bound::class,
            Saved::class,
            CompletedWithWindows::class,
        ]);

        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        WindowsAuthenticate::serverKey('REMOTE_USER');
        WindowsAuthenticate::username('userPrincipalName');
        WindowsAuthenticate::extractDomainsUsing(function ($accountName) {
            return $accountName;
        });
        WindowsAuthenticate::validateDomainsUsing(function ($user, $username) {
            return $user->getFirstAttribute('userPrincipalName') === $username;
        });

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
            'samaccountname' => 'jdoe',
            'userprincipalname' => 'jdoe@local.com',
            'objectguid' => $this->faker->uuid,
        ]);

        $request = tap(new Request, function ($request) use ($user) {
            $request->server->set('REMOTE_USER', $user->getFirstAttribute('userprincipalname'));
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $this->assertTrue(auth()->check());
            $this->assertEquals($user->getConvertedGuid(), auth()->user()->guid);
        });
    }
}
