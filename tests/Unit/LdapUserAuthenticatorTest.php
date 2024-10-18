<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Event;
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
use LdapRecord\Models\Model as LdapRecord;
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

        Event::fake();

        $this->assertTrue($auth->attempt($model, 'password'));

        Event::assertDispatched(Binding::class);
        Event::assertDispatched(Bound::class);
        Event::assertNotDispatched(BindFailed::class);
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

        Event::fake();

        $this->assertFalse($auth->attempt($model, 'password'));

        Event::assertDispatched(Binding::class);
        Event::assertDispatched(BindFailed::class);
        Event::assertNotDispatched(Bound::class);
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

        Event::fake();

        $this->assertFalse($auth->attempt($model, 'password'));

        Event::assertDispatched(Binding::class);
        Event::assertDispatched(Rejected::class);
        Event::assertNotDispatched(Bound::class);
        Event::assertNotDispatched(BindFailed::class);
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

        Event::fake();

        $this->assertTrue($auth->attempt($model, 'password'));

        Event::assertDispatched(Binding::class);
        Event::assertDispatched(Bound::class);
        Event::assertNotDispatched(Rejected::class);
        Event::assertNotDispatched(BindFailed::class);
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

class TestLdapAuthRuleWithEloquentModel implements Rule
{
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        return ! is_null($model);
    }
}

class TestFailingLdapAuthRule implements Rule
{
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        return false;
    }
}
