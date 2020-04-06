<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Models\Model;
use Mockery as m;

class LdapUserAuthenticatorTest extends TestCase
{
    public function test_attempt_passes()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $model = $this->getAuthenticatingModelMock($dn);
        $model->shouldReceive('getConnection')->once()->andReturn(
            m::mock(Connection::class, function ($connection) use ($dn) {
                $auth = m::mock(Guard::class);
                $auth->shouldReceive('attempt')->once()->withArgs([$dn, 'password'])->andReturnTrue();

                $connection->shouldReceive('auth')->once()->andReturn($auth);
            })
        );

        $auth = new LdapUserAuthenticator;

        $this->expectsEvents([
            Authenticating::class,
            Authenticated::class,
        ])->doesntExpectEvents([
            AuthenticationFailed::class,
        ]);

        $this->assertTrue($auth->attempt($model, 'password'));
    }

    public function test_attempt_failed()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $model = $this->getAuthenticatingModelMock($dn);
        $model->shouldReceive('getConnection')->once()->andReturn(
            m::mock(Connection::class, function ($connection) use ($dn) {
                $auth = m::mock(Guard::class);
                $auth->shouldReceive('attempt')->once()->withArgs([$dn, 'password'])->andReturnFalse();

                $connection->shouldReceive('auth')->once()->andReturn($auth);
            })
        );

        $auth = new LdapUserAuthenticator;

        $this->expectsEvents([
            Authenticating::class,
            AuthenticationFailed::class,
        ])->doesntExpectEvents([
            Authenticated::class,
        ]);

        $this->assertFalse($auth->attempt($model, 'password'));
    }

    public function test_auth_fails_due_to_rules()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $model = $this->getAuthenticatingModelMock($dn);
        $model->shouldReceive('getConnection')->once()->andReturn(
            m::mock(Connection::class, function ($connection) use ($dn) {
                $auth = m::mock(Guard::class);
                $auth->shouldReceive('attempt')->once()->withArgs([$dn, 'password'])->andReturnTrue();

                $connection->shouldReceive('auth')->once()->andReturn($auth);
            })
        );

        $auth = new LdapUserAuthenticator([TestFailingLdapAuthRule::class]);

        $this->expectsEvents([
            Authenticating::class,
            AuthenticationRejected::class,
        ])->doesntExpectEvents([
            Authenticated::class,
            AuthenticationFailed::class,
        ]);

        $this->assertFalse($auth->attempt($model, 'password'));
    }

    public function test_eloquent_model_can_be_set()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        $model = $this->getAuthenticatingModelMock($dn);
        $model->shouldReceive('getConnection')->once()->andReturn(
            m::mock(Connection::class, function ($connection) use ($dn) {
                $auth = m::mock(Guard::class);
                $auth->shouldReceive('attempt')->once()->withArgs([$dn, 'password'])->andReturnTrue();

                $connection->shouldReceive('auth')->once()->andReturn($auth);
            })
        );

        $auth = new LdapUserAuthenticator([TestLdapAuthRuleWithEloquentModel::class]);
        $auth->setEloquentModel(new TestUserModelStub());

        $this->expectsEvents([
            Authenticating::class,
            Authenticated::class,
        ])->doesntExpectEvents([
            AuthenticationRejected::class,
            AuthenticationFailed::class,
        ]);

        $this->assertTrue($auth->attempt($model, 'password'));
    }

    protected function getAuthenticatingModelMock($dn)
    {
        $model = m::mock(Model::class);
        $model->shouldReceive('getDn')->twice()->andReturn($dn);

        return $model;
    }
}

class TestLdapAuthRuleWithEloquentModel extends Rule
{
    public function isValid()
    {
        return isset($this->model);
    }
}

class TestFailingLdapAuthRule extends Rule
{
    public function isValid()
    {
        return false;
    }
}
