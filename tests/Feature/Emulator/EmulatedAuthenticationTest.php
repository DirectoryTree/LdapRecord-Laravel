<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

class EmulatedAuthenticationTest extends DatabaseTestCase
{
    use WithFaker;

    public function test_plain_ldap_authentication_passes()
    {
        $this->expectsEvents([
            Binding::class,
            Bound::class,
            DiscoveredWithCredentials::class,
        ])->doesntExpectEvents([
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

        $fake = DirectoryEmulator::setup();

        $this->setupPlainUserProvider();

        $user = LdapUser::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
        ]);

        $fake->actingAs($user);

        $this->assertTrue(Auth::attempt([
            'mail' => $user->mail[0],
            'password' => 'secret',
        ]));

        $model = Auth::user();

        $this->assertInstanceOf(LdapUser::class, $model);
        $this->assertTrue($user->is($model));
        $this->assertEquals($user->mail[0], $model->mail[0]);
        $this->assertEquals($user->getDn(), $model->getDn());
    }

    public function test_plain_ldap_authentication_fails()
    {
        $this->expectsEvents([
            Binding::class,
            DiscoveredWithCredentials::class,
        ])->doesntExpectEvents([
            Bound::class,
            Importing::class,
            Imported::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

        DirectoryEmulator::setup()->shouldBeConnected();

        $this->setupPlainUserProvider();

        $user = LdapUser::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
        ]);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));
    }
}
