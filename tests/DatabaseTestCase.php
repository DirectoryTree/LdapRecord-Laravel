<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Auth\EloquentUserProvider;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Model;
use Mockery as m;

class DatabaseTestCase extends TestCase
{
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
        LdapUserImporter $importer = null,
        EloquentUserProvider $eloquent = null
    ) {
        return new DatabaseUserProvider(
            $repo ?? $this->createLdapUserRepository(),
            $auth ?? $this->createLdapUserAuthenticator(),
            $importer ?? $this->createLdapUserImporter(),
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

    protected function createLdapUserImporter($eloquentModel = null, array $config = [])
    {
        return new LdapUserImporter($eloquentModel ?? TestUserModelStub::class, $config);
    }

    protected function createEloquentUserProvider($model = null)
    {
        return new EloquentUserProvider(app('hash'), $model ?? TestUserModelStub::class);
    }
}
