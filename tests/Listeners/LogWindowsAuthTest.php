<?php

namespace LdapRecord\Laravel\Tests\Listeners;

use Mockery as m;
use Adldap\Models\User;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Tests\TestCase;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Listeners\LogWindowsAuth;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;

class LogWindowsAuthTest extends TestCase
{
    /** @test */
    public function logged()
    {
        $l = new LogWindowsAuth();

        $user = m::mock(User::class);

        $name = 'John Doe';

        $user->shouldReceive('getCommonName')->andReturn($name);

        $e = new AuthenticatedWithWindows($user, m::mock(Authenticatable::class));

        $logged = "User '{$name}' has successfully authenticated via NTLM.";

        Log::shouldReceive('info')->once()->with($logged);

        $l->handle($e);
    }
}
