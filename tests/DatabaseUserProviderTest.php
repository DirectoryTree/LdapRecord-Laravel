<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Auth\EloquentUserProvider;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\ActiveDirectory\User;

class DatabaseUserProviderTest extends TestCase
{
    use CreatesTestUsers;

    public function test_importer_can_be_retrieved()
    {
        $importer = new LdapUserImporter(TestUser::class, []);
        $provider = new DatabaseUserProvider(
            $this->createLdapUserRepository(),
            $this->createLdapUserAuthenticator(),
            $importer,
            $this->createEloquentUserProvider()
        );
        $this->assertSame($importer, $provider->getLdapUserImporter());
    }

    public function test_retrieve_by_id_uses_eloquent()
    {
        $model = $this->createTestUser([
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'secret',
        ]);

        $provider = new DatabaseUserProvider(
            $this->createLdapUserRepository(),
            $this->createLdapUserAuthenticator(),
            $this->createLdapUserImporter(),
            $this->createEloquentUserProvider()
        );

        $this->assertTrue($model->is($provider->retrieveById($model->id)));
    }

    public function test_retrieve_by_token_uses_eloquent()
    {
        $model = $this->createTestUser([
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'secret',
            'remember_token' => 'token',
        ]);

        $provider = new DatabaseUserProvider(
            $this->createLdapUserRepository(),
            $this->createLdapUserAuthenticator(),
            $this->createLdapUserImporter(),
            $this->createEloquentUserProvider()
        );

        $this->assertTrue($model->is($provider->retrieveByToken($model->id, $model->remember_token)));
    }

    public function test_update_remember_token_uses_eloquent()
    {
        $model = $this->createTestUser([
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'secret',
            'remember_token' => 'token',
        ]);

        $provider = new DatabaseUserProvider(
            $this->createLdapUserRepository(),
            $this->createLdapUserAuthenticator(),
            $this->createLdapUserImporter(),
            $this->createEloquentUserProvider()
        );

        $provider->updateRememberToken($model, 'new-token');
        $this->assertEquals('new-token', $model->fresh()->remember_token);
    }

    protected function createLdapUserRepository($model = null)
    {
        return new LdapUserRepository($model ?? User::class);
    }

    protected function createLdapUserAuthenticator()
    {
        return new LdapUserAuthenticator();
    }

    protected function createLdapUserImporter($eloquentModel = null, array $config = [])
    {
        return new LdapUserImporter($eloquentModel ?? TestUser::class, $config);
    }

    protected function createEloquentUserProvider($model = null)
    {
        return new EloquentUserProvider(app('hash'), $model ?? TestUser::class);
    }
}
