<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\LdapAuthServiceProvider;
use LdapRecord\Laravel\LdapServiceProvider;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
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
        ];
    }

    /**
     * Define the environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetup($app)
    {
        $config = $app['config'];

        // Database setup.
        $config->set('database.default', 'testbench');
        $config->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        // LDAP mock setup.
        $config->set('ldap.logging', false);
        $config->set('ldap.default', 'default');
        $config->set('ldap.connections.default', [
            'hosts' => ['localhost'],
            'username' => 'user',
            'password' => 'secret',
            'base_dn' => 'dc=local,dc=com',
            'port' => 389,
        ]);
    }

    protected function setupPlainUserProvider(array $config = [])
    {
        config()->set('auth.defaults.guard', 'ldap-plain');
        config()->set('auth.guards.ldap-plain', [
            'driver' => 'session',
            'provider' => 'ldap-plain',
        ]);
        config()->set('auth.providers.ldap-plain', array_merge_recursive([
            'driver' => 'ldap',
            'rules' => [],
            'model' => \LdapRecord\Models\ActiveDirectory\User::class,
        ], $config));
    }

    protected function setupDatabaseUserProvider(array $config = [])
    {
        config()->set('auth.defaults.guard', 'ldap-database');
        config()->set('auth.guards.ldap-database', [
            'driver' => 'session',
            'provider' => 'ldap-database',
        ]);
        config()->set('auth.providers.ldap-database', array_merge_recursive([
            'driver' => 'ldap',
            'rules' => [],
            'model' => \LdapRecord\Models\ActiveDirectory\User::class,
            'database' => [
                'model' => \LdapRecord\Laravel\Tests\TestUserModelStub::class,
                'sync_passwords' => true,
                'sync_attributes' => [
                    'name' => 'cn',
                    'email' => 'mail',
                ],
            ],
        ], $config));
    }
}
