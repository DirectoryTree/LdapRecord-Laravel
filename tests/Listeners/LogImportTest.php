<?php

namespace LdapRecord\Laravel\Tests\Listeners;

use Mockery as m;
use Adldap\Models\User;
use LdapRecord\Laravel\Tests\TestCase;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Listeners\LogImport;
use LdapRecord\Laravel\Tests\Models\TestUser;

class LogImportTest extends TestCase
{
    /** @test */
    public function logged()
    {
        $l = new LogImport();

        $user = m::mock(User::class);
        $model = m::mock(TestUser::class);

        $name = 'John Doe';

        $user->shouldReceive('getCommonName')->andReturn($name);

        $e = new Importing($user, $model);

        $logged = "User '{$name}' is being imported.";

        Log::shouldReceive('info')->once()->with($logged);

        $l->handle($e);
    }
}
