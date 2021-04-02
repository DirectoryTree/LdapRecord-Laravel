<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Laravel\Events\Auth\BindFailed;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\Rejected;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\Tests\TestCase;
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
            Binding::class,
            Bound::class,
        ])->doesntExpectEvents([
            BindFailed::class,
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
            Binding::class,
            BindFailed::class,
        ])->doesntExpectEvents([
            Bound::class,
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
            Binding::class,
            Rejected::class,
        ])->doesntExpectEvents([
            Bound::class,
            BindFailed::class,
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

        $auth->setEloquentModel(new TestLdapUserAuthenticatedModelStub);

        $this->expectsEvents([
            Binding::class,
            Bound::class,
        ])->doesntExpectEvents([
            Rejected::class,
            BindFailed::class,
        ]);

        $this->assertTrue($auth->attempt($model, 'password'));
    }

    protected function getAuthenticatingModelMock($dn)
    {
        $model = m::mock(Model::class);
        $model->shouldReceive('getDn')->once()->andReturn($dn);

        return $model;
    }
}

class TestLdapUserAuthenticatedModelStub extends EloquentModel
{
    //
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
