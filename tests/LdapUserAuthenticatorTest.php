<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\Validation\Rules\Rule;
use LdapRecord\Models\Entry;
use Mockery as m;

class DomainAuthenticatorTest extends TestCase
{
    public function test_attempt_passes()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $model = new Entry();
        $model->setDn($dn);

        $connection = m::mock(Connection::class, function ($connection) use ($dn) {
            $auth = m::mock(Guard::class);
            $auth->shouldReceive('attempt')->once()->withArgs([$dn, 'password'])->andReturnTrue();

            $connection->shouldReceive('auth')->once()->andReturn($auth);
        });

        $auth = new LdapUserAuthenticator($connection);

        $this->expectsEvents([
            Authenticating::class,
            Authenticated::class,
        ]);

        $this->doesntExpectEvents([AuthenticationFailed::class]);

        $this->assertTrue($auth->attempt($model, 'password'));
    }

    public function test_attempt_failed()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $model = new Entry();
        $model->setDn($dn);

        $connection = m::mock(Connection::class, function ($connection) use ($dn) {
            $auth = m::mock(Guard::class);
            $auth->shouldReceive('attempt')->once()->withArgs([$dn, 'password'])->andReturnFalse();

            $connection->shouldReceive('auth')->once()->andReturn($auth);
        });

        $auth = new LdapUserAuthenticator($connection);

        $this->expectsEvents([
            Authenticating::class,
            AuthenticationFailed::class,
        ]);

        $this->doesntExpectEvents([Authenticated::class]);

        $this->assertFalse($auth->attempt($model, 'password'));
    }

    public function test_auth_fails_due_to_rules()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $model = new Entry();
        $model->setDn($dn);

        $connection = m::mock(Connection::class, function ($connection) use ($dn) {
            $auth = m::mock(Guard::class);
            $auth->shouldReceive('attempt')->once()->withArgs([$dn, 'password'])->andReturnTrue();

            $connection->shouldReceive('auth')->once()->andReturn($auth);
        });

        $auth = new LdapUserAuthenticator($connection, [TestLdapAuthRule::class]);

        $this->expectsEvents([
            Authenticating::class,
            Authenticated::class,
            AuthenticationRejected::class,
        ]);

        $this->doesntExpectEvents([AuthenticationFailed::class]);

        $this->assertFalse($auth->attempt($model, 'password'));
    }
}

class TestLdapAuthRule extends Rule
{
    public function isValid()
    {
        return false;
    }
}
