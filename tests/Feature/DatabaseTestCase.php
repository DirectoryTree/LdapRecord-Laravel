<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Import\UserSynchronizer;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Model;
use Mockery as m;
use Orchestra\Testbench\Attributes\WithMigration;

#[WithMigration]
class DatabaseTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
        $this->loadMigrationsFrom(__DIR__.'/../../vendor/laravel/sanctum/database/migrations');
    }

    protected function getMockLdapModel(array $attributes = [])
    {
        $ldapModel = m::mock(Model::class);
        $ldapModel->shouldReceive('getName')->andReturn('name');
        $ldapModel->shouldReceive('getConvertedGuid')->andReturn('guid');
        $ldapModel->shouldReceive('getConnectionName')->andReturn('default');

        foreach ($attributes as $name => $value) {
            $ldapModel->shouldReceive('getFirstAttribute')->withArgs([$name])->andReturn($value);
        }

        return $ldapModel;
    }

    protected function createDatabaseUserProvider(
        ?LdapUserRepository $repo = null,
        ?LdapUserAuthenticator $auth = null,
        ?UserSynchronizer $synchronizer = null,
        ?EloquentUserProvider $eloquent = null
    ) {
        return new DatabaseUserProvider(
            $repo ?? $this->createLdapUserRepository(),
            $auth ?? $this->createLdapUserAuthenticator(),
            $synchronizer ?? $this->createLdapUserSynchronizer(),
            $eloquent ?? $this->createEloquentUserProvider()
        );
    }

    protected function createLdapUserRepository($model = null)
    {
        return new LdapUserRepository($model ?? User::class);
    }

    protected function createLdapUserAuthenticator()
    {
        return new LdapUserAuthenticator;
    }

    protected function createLdapUserSynchronizer($eloquentModel = null, array $config = [])
    {
        return new UserSynchronizer($eloquentModel ?? TestUserModelStub::class, $config);
    }

    protected function createEloquentUserProvider($model = null)
    {
        return new EloquentUserProvider(app('hash'), $model ?? TestUserModelStub::class);
    }
}
