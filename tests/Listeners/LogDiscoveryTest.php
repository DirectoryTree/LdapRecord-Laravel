<?php

namespace LdapRecord\Laravel\Tests\Listeners;

use Mockery as m;
use Adldap\Models\User;
use LdapRecord\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Listeners\LogDiscovery;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;

class LogDiscoveryTest extends TestCase
{
    /** @test */
    public function logged()
    {
        $l = new LogDiscovery();

        $user = m::mock(User::class);

        $name = 'John Doe';

        $user->shouldReceive('getCommonName')->andReturn($name);

        $e = new DiscoveredWithCredentials($user);

        $logged = "User '{$name}' has been successfully found for authentication.";

        Log::shouldReceive('info')->once()->with($logged);

        $l->handle($e);
    }
}
