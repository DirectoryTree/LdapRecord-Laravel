<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Import\UserSynchronizer;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Model;
use Mockery as m;

class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createUsersTable();
    }

    protected function createUsersTable()
    {
        // Setup the users database table.
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();

            // Additional fields for LdapRecord.
            $table->string('guid')->unique()->nullable();
            $table->string('domain')->nullable();
        });
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
        LdapUserRepository $repo = null,
        LdapUserAuthenticator $auth = null,
        UserSynchronizer $synchronizer = null,
        EloquentUserProvider $eloquent = null
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
        return new LdapUserAuthenticator();
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
