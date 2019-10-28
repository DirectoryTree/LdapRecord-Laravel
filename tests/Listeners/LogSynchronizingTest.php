<?php

namespace LdapRecord\Laravel\Tests\Listeners;

use Mockery as m;
use Adldap\Models\User;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Laravel\Tests\Models\TestUser;
use LdapRecord\Laravel\Listeners\LogSynchronizing;

class LogSynchronizingTest extends TestCase
{
    /** @test */
    public function logged()
    {
        $l = new LogSynchronizing();

        $user = m::mock(User::class);
        $model = m::mock(TestUser::class);

        $name = 'John Doe';

        $user->shouldReceive('getCommonName')->andReturn($name);

        $e = new Synchronizing($user, $model);

        $logged = "User '{$name}' is being synchronized.";

        Log::shouldReceive('info')->once()->with($logged);

        $l->handle($e);
    }
}
