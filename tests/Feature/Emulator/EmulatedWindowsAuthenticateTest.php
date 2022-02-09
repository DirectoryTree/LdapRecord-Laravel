<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\CompletedWithWindows;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\Middleware\UserDomainValidator;
use LdapRecord\Laravel\Middleware\WindowsAuthenticate;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Laravel\Tests\Feature\TestUserModelStub;
use LdapRecord\Models\ActiveDirectory\User;

class EmulatedWindowsAuthenticateTest extends DatabaseTestCase
{
    use WithFaker;

    protected function tearDown(): void
    {
        // Reset all static properties.
        WindowsAuthenticate::$guards = null;
        WindowsAuthenticate::$serverKey = 'AUTH_USER';
        WindowsAuthenticate::$username = 'samaccountname';
        WindowsAuthenticate::$domainVerification = true;
        WindowsAuthenticate::$logoutUnauthenticatedUsers = false;
        WindowsAuthenticate::$rememberAuthenticatedUsers = false;
        WindowsAuthenticate::$userDomainExtractor = null;
        WindowsAuthenticate::$userDomainValidator = UserDomainValidator::class;

        parent::tearDown();
    }

    public function test_windows_authenticated_user_is_signed_in()
    {
        Event::fake();

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

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Imported::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);
        Event::assertDispatched(Binding::class);
        Event::assertDispatched(Bound::class);
        Event::assertDispatched(Saved::class);
        Event::assertDispatched(CompletedWithWindows::class);
    }

    public function test_kerberos_authenticated_user_is_signed_in()
    {
        Event::fake();

        DirectoryEmulator::setup();

        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        WindowsAuthenticate::serverKey('REMOTE_USER');
        WindowsAuthenticate::username('userPrincipalName');
        WindowsAuthenticate::extractDomainUsing(function ($accountName) {
            return $accountName;
        });
        WindowsAuthenticate::validateDomainUsing(function ($user, $username) {
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

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Imported::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);
        Event::assertDispatched(Binding::class);
        Event::assertDispatched(Bound::class);
        Event::assertDispatched(Saved::class);
        Event::assertDispatched(CompletedWithWindows::class);
    }
}
