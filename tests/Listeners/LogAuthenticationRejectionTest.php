<?php

namespace LdapRecord\Laravel\Tests\Listeners;

use Mockery as m;
use Adldap\Models\User;
use LdapRecord\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\Listeners\LogAuthenticationRejection;

class LogAuthenticationRejectionTest extends TestCase
{
    /** @test */
    public function logged()
    {
        $l = new LogAuthenticationRejection();

        $user = m::mock(User::class);

        $name = 'John Doe';

        $user->shouldReceive('getCommonName')->andReturn($name);

        $e = new AuthenticationRejected($user);

        $logged = "User '{$name}' has failed validation. They have been denied authentication.";

        Log::shouldReceive('info')->once()->with($logged);

        $l->handle($e);
    }
}
