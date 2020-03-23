<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Http\Request;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Middleware\WindowsAuthenticate;
use LdapRecord\Models\ActiveDirectory\User;
use Mockery as m;

class WindowsAuthMiddlewareTest extends TestCase
{
    use CreatesTestUsers;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset middleware.
        WindowsAuthenticate::$domainVerification = true;
        WindowsAuthenticate::$serverKey = 'AUTH_USER';
        WindowsAuthenticate::$username = 'samaccountname';
    }

    public function test_request_continues_if_user_is_not_set_in_the_server_params()
    {
        $this->doesntExpectEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
            AuthenticatedWithWindows::class,
        ]);

        $middleware = new WindowsAuthenticate(app('auth'));

        $request = new Request;
        $middleware->handle($request, function () {
            $this->assertTrue(true);
        });
    }

    public function test_request_continues_if_user_is_already_logged_in()
    {
        $this->doesntExpectEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
            AuthenticatedWithWindows::class,
        ]);

        auth()->login($this->createTestUser([
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'password' => 'secret',
        ]));

        $middleware = new WindowsAuthenticate(app('auth'));
        $request = new Request;
        $middleware->handle($request, function () {
            $this->assertTrue(true);
        });
    }

    public function test_request_continues_if_ldap_user_cannot_be_located()
    {
        $this->doesntExpectEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
            AuthenticatedWithWindows::class,
        ]);

        $this->setupDatabaseUserProvider();

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturnNull();

        $auth = app('auth');
        $auth->guard()->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        $middleware->handle($request, function () {
            $this->assertTrue(true);
            $this->assertFalse(auth()->check());
        });
    }

    public function test_authenticates_with_database_user_provider()
    {
        $this->expectsEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
            AuthenticatedWithWindows::class,
        ]);

        $this->setupDatabaseUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'mail' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $auth = app('auth');
        $guard = $auth->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $model = $guard->user();
            $this->assertTrue($model->exists);
            $this->assertTrue($model->wasRecentlyCreated);
            $this->assertEquals($user->getConvertedGuid(), $model->guid);
            $this->assertEquals($user->getFirstAttribute('cn'), $model->name);
            $this->assertEquals($user->getFirstAttribute('mail'), $model->email);
            $this->assertSame(auth()->user(), $model);
        });
    }

    public function test_authenticates_with_plain_user_provider()
    {
        $this->expectsEvents([AuthenticatedWithWindows::class]);
        $this->doesntExpectEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $auth = app('auth');
        $guard = $auth->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $this->assertSame($guard->user(), $user);
        });
    }

    public function test_server_key_can_be_set()
    {
        WindowsAuthenticate::serverKey('FOO');

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $auth = app('auth');
        $guard = $auth->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('FOO', 'Local\SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $this->assertSame($guard->user(), $user);
        });
    }

    public function test_username_can_be_set()
    {
        WindowsAuthenticate::username('foo');

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['foo', 'SteveBauman'])->andReturn($user);

        $auth = app('auth');
        $guard = $auth->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $this->assertSame($guard->user(), $user);
        });
    }

    public function test_domain_verification_is_enabled_by_default()
    {
        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $auth = app('auth');
        $guard = $auth->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $this->assertNull($guard->user());
        });
    }

    public function test_domain_verification_can_be_disabled()
    {
        WindowsAuthenticate::bypassDomainVerification();

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $auth = app('auth');
        $guard = $auth->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $this->assertSame($guard->user(), $user);
        });
    }

    public function test_authentication_rules_are_used()
    {
        $this->setupPlainUserProvider([
            'rules' => [WindowsAuthRuleStub::class],
        ]);

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $auth = app('auth');
        $guard = $auth->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($auth);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        $middleware->handle($request, function () use ($guard) {
            $this->assertNull($guard->user());

            $this->assertTrue($_SERVER[WindowsAuthRuleStub::class]);
        });
    }
}

class WindowsAuthRuleStub extends Rule
{
    public function isValid()
    {
        $_SERVER[self::class] = true;

        return false;
    }
}
