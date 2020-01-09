<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Entry;
use Mockery as m;
use Illuminate\Http\Request;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Middleware\WindowsAuthenticate;

class WindowsAuthMiddlewareTest extends TestCase
{
    public function test_authenticates_with_database_user_provider()
    {
        $this->setupDatabaseUserProvider();

        $user = new Entry;
        $user->cn = 'SteveBauman';
        $user->userprincipalname = 'sbauman@test.com';
        $user->objectguid = 'bf9679e7-0de6-11d0-a285-00aa003049e2';

        $users = m::mock(LdapUserRepository::class);
        $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

        $guard = app('auth')->guard();
        $guard->getProvider()->setLdapUserRepository($users);

        $middleware = new WindowsAuthenticate($guard);

        $request = new Request;
        $request->server->set('AUTH_USER', 'SteveBauman');

        $middleware->handle($request, function ($request) use ($user, $guard) {
            $model = $guard->user();
            $this->assertTrue($model->exists);
            $this->assertTrue($model->wasRecentlyCreated);
            $this->assertEquals($user->getConvertedGuid(), $model->guid);
            $this->assertEquals($user->getFirstAttribute('cn'), $model->name);
            $this->assertEquals($user->getFirstAttribute('userprincipalname'), $model->email);
            $this->assertSame(Auth::user(), $model);
        });
    }
}
