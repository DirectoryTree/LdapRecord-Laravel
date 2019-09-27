<?php

namespace LdapRecord\Laravel\Tests\Listeners;

use Mockery as m;
use Adldap\Models\User;
use LdapRecord\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\AuthenticationSuccessful;
use LdapRecord\Laravel\Listeners\LogAuthenticationSuccess;

class LogAuthenticationSuccessTest extends TestCase
{
    /** @test */
    public function logged()
    {
        $l = new LogAuthenticationSuccess();

        $user = m::mock(User::class);

        $name = 'John Doe';

        $user->shouldReceive('getCommonName')->andReturn($name);

        $e = new AuthenticationSuccessful($user);

        $logged = "User '{$name}' has been successfully logged in.";

        Log::shouldReceive('info')->once()->with($logged);

        $l->handle($e);
    }
}
