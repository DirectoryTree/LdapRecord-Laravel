<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\SanctumServiceProvider;
use LdapRecord\Laravel\LdapAuthServiceProvider;
use LdapRecord\Laravel\LdapServiceProvider;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        DirectoryEmulator::tearDown();

        parent::tearDown();
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        Hash::setRounds(4);

        return $app;
    }

    protected function getPackageProviders($app)
    {
        return [
            LdapServiceProvider::class,
            LdapAuthServiceProvider::class,
            SanctumServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetup($app)
    {
        $config = $app['config'];

        // Database setup.
        $config->set('database.default', 'testbench');
        $config->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
        ]);

        // LDAP mock setup.
        $config->set('ldap.default', 'default');
        $config->set('ldap.logging.enabled', false);
        $config->set('ldap.connections.default', [
            'hosts' => ['localhost'],
            'username' => 'user',
            'password' => 'secret',
            'base_dn' => 'dc=local,dc=com',
            'port' => 389,
        ]);
    }

    protected function setupLdapUserProvider($guardName, array $config)
    {
        config()->set('auth.defaults.guard', $guardName);

        config()->set("auth.guards.$guardName", [
            'driver' => 'session',
            'provider' => $guardName,
        ]);

        config()->set("auth.providers.$guardName", array_merge([
            'rules' => [],
            'driver' => 'ldap',
            'model' => User::class,
        ], $config));
    }

    protected function setupPlainUserProvider(array $config = [])
    {
        $this->setupLdapUserProvider('ldap-plain', $config);
    }

    protected function setupDatabaseUserProvider(array $config = [])
    {
        $this->setupLdapUserProvider('ldap-database', array_merge([
            'database' => [
                'sync_attributes' => [
                    'name' => 'cn',
                    'email' => 'mail',
                ],
            ],
        ], $config));
    }
}
