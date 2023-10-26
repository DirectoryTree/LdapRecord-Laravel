<?php

namespace LdapRecord\Laravel\Tests\Unit;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\LdapServiceProvider;
use LdapRecord\Laravel\Tests\TestCase;

class LdapServiceProviderTest extends TestCase
{
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $_ENV['LDAP_CONNECTIONS'] = 'alpha,bravo';
        $_ENV['LDAP_ALPHA_HOSTS'] = '10.0.0.1,10.0.0.2';
        $_ENV['LDAP_ALPHA_USERNAME'] = 'cn=user,dc=alpha,dc=com';
        $_ENV['LDAP_ALPHA_PASSWORD'] = 'alpha-secret';
        $_ENV['LDAP_ALPHA_BASE_DN'] = 'dc=alpha,dc=com';

        $_ENV['LDAP_BRAVO_HOSTS'] = '172.0.0.1,172.0.0.2';
        $_ENV['LDAP_BRAVO_USERNAME'] = 'cn=user,dc=bravo,dc=com';
        $_ENV['LDAP_BRAVO_PASSWORD'] = 'bravo-secret';
        $_ENV['LDAP_BRAVO_BASE_DN'] = 'dc=bravo,dc=com';
        $_ENV['LDAP_BRAVO_OPT_X_TLS_CACERTFILE'] = '/path';
        $_ENV['LDAP_BRAVO_OPT_X_TLS_CERTFILE'] = '/path';
    }

    protected function tearDown(): void
    {
        unset($_ENV['LDAP_CONNECTIONS']);
        unset($_ENV['LDAP_ALPHA_HOSTS']);
        unset($_ENV['LDAP_ALPHA_USERNAME']);
        unset($_ENV['LDAP_ALPHA_PASSWORD']);
        unset($_ENV['LDAP_ALPHA_BASE_DN']);

        unset($_ENV['LDAP_BRAVO_HOSTS']);
        unset($_ENV['LDAP_BRAVO_USERNAME']);
        unset($_ENV['LDAP_BRAVO_PASSWORD']);
        unset($_ENV['LDAP_BRAVO_BASE_DN']);
        unset($_ENV['LDAP_BRAVO_OPT_X_TLS_CACERTFILE']);
        unset($_ENV['LDAP_BRAVO_OPT_X_TLS_CERTFILE']);

        parent::tearDown();
    }

    public function test_config_is_publishable()
    {
        $this->artisan('vendor:publish', ['--provider' => LdapServiceProvider::class, '--no-interaction' => true]);

        $this->assertFileExists(config_path('ldap.php'));
    }

    public function test_connections_from_environment_variables_are_setup()
    {
        tap(Container::getConnection('alpha'), function (Connection $connection) {
            $config = $connection->getConfiguration();

            $this->assertEquals(['10.0.0.1', '10.0.0.2'], $config->get('hosts'));
            $this->assertEquals('dc=alpha,dc=com', $config->get('base_dn'));
            $this->assertEquals('cn=user,dc=alpha,dc=com', $config->get('username'));
            $this->assertEquals('alpha-secret', $config->get('password'));
        });

        tap(Container::getConnection('bravo'), function (Connection $connection) {
            $config = $connection->getConfiguration();

            $this->assertEquals(['172.0.0.1', '172.0.0.2'], $config->get('hosts'));
            $this->assertEquals('dc=bravo,dc=com', $config->get('base_dn'));
            $this->assertEquals('cn=user,dc=bravo,dc=com', $config->get('username'));
            $this->assertEquals('bravo-secret', $config->get('password'));
        });
    }

    public function test_custom_connection_options_from_env_are_loaded_into_configuration()
    {
        tap(Container::getConnection('bravo'), function (Connection $connection) {
            $config = $connection->getConfiguration();

            $this->assertEquals([
                LDAP_OPT_X_TLS_CACERTFILE => '/path',
                LDAP_OPT_X_TLS_CERTFILE => '/path',
            ], $config->get('options'));
        });
    }

    public function test_env_config_is_loaded_and_cacheable()
    {
        $this->assertEquals([
            'default' => 'default',
            'connections' => [
                'default' => [
                    'hosts' => ['localhost'],
                    'username' => 'user',
                    'password' => 'secret',
                    'base_dn' => 'dc=local,dc=com',
                    'port' => 389,
                ],
                'alpha' => [
                    'hosts' => ['10.0.0.1', '10.0.0.2'],
                    'username' => 'cn=user,dc=alpha,dc=com',
                    'password' => 'alpha-secret',
                    'base_dn' => 'dc=alpha,dc=com',
                    'port' => 389,
                    'timeout' => 5,
                ],
                'bravo' => [
                    'hosts' => ['172.0.0.1', '172.0.0.2'],
                    'username' => 'cn=user,dc=bravo,dc=com',
                    'password' => 'bravo-secret',
                    'base_dn' => 'dc=bravo,dc=com',
                    'port' => 389,
                    'timeout' => 5,
                    'options' => [
                        24578 => '/path',
                        24580 => '/path',
                    ],
                ],
            ],
            'logging' => [
                'enabled' => false,
                'channel' => 'stack',
                'level' => 'info',
            ],
            'cache' => [
                'enabled' => false,
                'driver' => 'file',
            ],
        ], app('config')->all()['ldap']);
    }
}
