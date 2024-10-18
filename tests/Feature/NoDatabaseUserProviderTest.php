<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Exception;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\LdapRecord;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Entry;
use Mockery as m;

class NoDatabaseUserProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        // Reset static properties.
        LdapRecord::$failingQuietly = true;

        parent::tearDown();
    }

    public function test_user_repository_can_be_retrieved()
    {
        $repo = new LdapUserRepository(User::class);

        $provider = new NoDatabaseUserProvider($repo, new LdapUserAuthenticator);

        $this->assertSame($repo, $provider->getLdapUserRepository());
    }

    public function test_user_authenticator_can_be_retrieved()
    {
        $auth = new LdapUserAuthenticator;

        $provider = new NoDatabaseUserProvider(new LdapUserRepository(User::class), $auth);

        $this->assertSame($auth, $provider->getLdapUserAuthenticator());
    }

    public function test_retrieve_by_id_returns_model_instance()
    {
        $model = new Entry;

        $repo = m::mock(LdapUserRepository::class);

        $repo->shouldReceive('findByGuid')->once()->withArgs(['id'])->andReturn($model);

        $provider = new NoDatabaseUserProvider($repo, new LdapUserAuthenticator);

        $this->assertSame($model, $provider->retrieveById('id'));
    }

    public function test_retrieve_by_credentials_returns_model_instance()
    {
        $model = new Entry;

        $repo = m::mock(LdapUserRepository::class);

        $repo->shouldReceive('findByCredentials')->once()->withArgs([['username' => 'foo']])->andReturn($model);

        $provider = new NoDatabaseUserProvider($repo, new LdapUserAuthenticator);

        $this->assertSame($model, $provider->retrieveByCredentials(['username' => 'foo']));
    }

    public function test_validate_credentials_attempts_authentication()
    {
        $user = new User;

        $auth = m::mock(LdapUserAuthenticator::class);

        $auth->shouldReceive('attempt')->once()->withArgs([$user, 'secret'])->andReturnTrue();

        $provider = new NoDatabaseUserProvider(new LdapUserRepository(Entry::class), $auth);

        $this->assertTrue($provider->validateCredentials($user, ['password' => 'secret']));
    }

    public function test_failing_loudly_throws_exception_when_resolving_users()
    {
        LdapRecord::failLoudly();

        $provider = m::mock(NoDatabaseUserProvider::class)->makePartial();

        $provider->resolveUsersUsing(function () {
            throw new Exception('Failed');
        });

        $this->expectExceptionMessage('Failed');

        $provider->retrieveByCredentials([]);
    }
}
