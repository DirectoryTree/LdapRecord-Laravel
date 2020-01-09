<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Http\Request;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Middleware\WindowsAuthenticate;
use LdapRecord\Models\ActiveDirectory\User;
use Mockery as m;

class WindowsAuthMiddlewareTest extends TestCase
{
    use CreatesTestUsers;

    public function test_request_continues_if_user_is_not_set_in_the_server_params()
    {
        $guard = app('auth')->guard();
        $middleware = new WindowsAuthenticate($guard);

        $request = new Request;
        $middleware->handle($request, function () {
            $this->assertTrue(true);
        });
    }

    public function test_request_continues_if_user_is_already_logged_in()
    {
        auth()->login($this->createTestUser([
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'password' => 'secret',
        ]));

        $middleware = new WindowsAuthenticate(app('auth')->guard());
        $request = new Request;
        $middleware->handle($request, function () {
            $this->assertTrue(true);
        });
    }

    public function test_request_continues_if_ldap_user_cannot_be_located()
    {
        $this->setupDatabaseUserProvider();

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturnNull();

        $guard = app('auth')->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($guard);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        $middleware->handle($request, function () {
            $this->assertTrue(true);
            $this->assertFalse(auth()->check());
        });
    }

    public function test_authenticates_with_database_user_provider()
    {
        $this->setupDatabaseUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@test.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $guard = app('auth')->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($guard);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $model = $guard->user();
            $this->assertTrue($model->exists);
            $this->assertTrue($model->wasRecentlyCreated);
            $this->assertEquals($user->getConvertedGuid(), $model->guid);
            $this->assertEquals($user->getFirstAttribute('cn'), $model->name);
            $this->assertEquals($user->getFirstAttribute('userprincipalname'), $model->email);
            $this->assertSame(auth()->user(), $model);
        });
    }

    public function test_authenticates_with_plain_user_provider()
    {
        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@test.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $guard = app('auth')->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($guard);

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        $middleware->handle($request, function () use ($user, $guard) {
            $this->assertSame(auth()->user(), $user);
        });
    }
}
