<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
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
        Event::fake();

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

        Event::assertDispatched(Binding::class);
        Event::assertDispatched(Bound::class);
        Event::assertDispatched(DiscoveredWithCredentials::class);

        Event::assertNotDispatched(Importing::class);
        Event::assertNotDispatched(Imported::class);
        Event::assertNotDispatched(Synchronizing::class);
        Event::assertNotDispatched(Synchronized::class);
    }

    public function test_plain_ldap_authentication_fails()
    {
        Event::fake();

        DirectoryEmulator::setup()->shouldBeConnected();

        $this->setupPlainUserProvider();

        $user = LdapUser::create([
            'cn' => 'John',
            'mail' => 'jdoe@email.com',
        ]);

        $this->assertFalse(Auth::attempt(['mail' => $user->mail[0], 'password' => 'secret']));

        Event::assertDispatched(Binding::class);
        Event::assertDispatched(DiscoveredWithCredentials::class);

        Event::assertNotDispatched(Bound::class);
        Event::assertNotDispatched(Importing::class);
        Event::assertNotDispatched(Imported::class);
        Event::assertNotDispatched(Synchronizing::class);
        Event::assertNotDispatched(Synchronized::class);
    }
}
