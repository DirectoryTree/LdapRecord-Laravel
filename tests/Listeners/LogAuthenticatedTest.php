<?php

namespace LdapRecord\Laravel\Tests\Listeners;

use Mockery as m;
use Adldap\Models\User;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Listeners\LogAuthenticated;

class LogAuthenticatedTest extends TestCase
{
    /** @test */
    public function logged()
    {
        $l = new LogAuthenticated();

        $user = m::mock(User::class);

        $user->shouldReceive('getCommonName')->andReturn('jdoe');

        $e = new Authenticated($user);

        $logged = "User 'jdoe' has successfully passed LDAP authentication.";

        Log::shouldReceive('info')->once()->with($logged);

        $l->handle($e);
    }
}
