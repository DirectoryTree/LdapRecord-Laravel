<?php

namespace LdapRecord\Laravel\Tests\Emulator;

use Illuminate\Http\Request;
use Illuminate\Foundation\Testing\WithFaker;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\Events\Auth\CompletedWithWindows;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\DatabaseProviderTestCase;
use LdapRecord\Laravel\Middleware\WindowsAuthenticate;

class EmulatedWindowsAuthenticateTest extends DatabaseProviderTestCase
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

        $this->setupDatabaseUserProvider();

        $user = User::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
            'samaccountname' => 'jdoe',
            'objectguid' => $this->faker->uuid,
        ]);

        $middleware = new WindowsAuthenticate(app('auth'));

        $request = tap(new Request, function ($request) use ($user) {
            $request->server->set('AUTH_USER', 'LOCAL\\'.$user->getFirstAttribute('samaccountname'));
        });

        $middleware->handle($request, function () use ($user) {
            $this->assertTrue(auth()->check());
            $this->assertEquals($user->getConvertedGuid(), auth()->user()->guid);
        });
    }
}
