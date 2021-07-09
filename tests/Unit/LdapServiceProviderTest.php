<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Log\LogManager;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\LdapServiceProvider;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Query\Cache;

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
        $_ENV['LDAP_ALPHA_CACHE'] = 'true';

        $_ENV['LDAP_BRAVO_HOSTS'] = '172.0.0.1,172.0.0.2';
        $_ENV['LDAP_BRAVO_USERNAME'] = 'cn=user,dc=bravo,dc=com';
        $_ENV['LDAP_BRAVO_PASSWORD'] = 'bravo-secret';
        $_ENV['LDAP_BRAVO_BASE_DN'] = 'dc=bravo,dc=com';
        $_ENV['LDAP_BRAVO_OPT_X_TLS_CACERTFILE'] = '/path';
        $_ENV['LDAP_BRAVO_OPT_X_TLS_CERTFILE'] = '/path';

        $app['config']->set('ldap.logging', true);
        $app['config']->set('ldap.cache.enabled', true);
        $app['config']->set('ldap.cache.driver', 'array');
    }

    public function test_config_is_publishable()
    {
        $this->artisan('vendor:publish', ['--provider' => LdapServiceProvider::class, '--no-interaction' => true]);

        $this->assertFileExists(config_path('ldap.php'));
    }

    public function test_logger_is_set_on_container_when_enabled()
    {
        $this->assertInstanceOf(LogManager::class, Container::getInstance()->getLogger());
    }

    public function test_cache_is_set_on_connection_when_enabled()
    {
        $this->assertInstanceOf(Cache::class, Container::getInstance()->get('default')->getCache());
    }

    public function test_connections_from_environment_variables_are_setup()
    {
        tap(Container::getConnection('alpha'), function (Connection $connection) {
            $config = $connection->getConfiguration();

            $this->assertEquals(['10.0.0.1', '10.0.0.2'], $config->get('hosts'));
            $this->assertEquals('dc=alpha,dc=com', $config->get('base_dn'));
            $this->assertEquals('cn=user,dc=alpha,dc=com', $config->get('username'));
            $this->assertEquals('alpha-secret', $config->get('password'));

            $this->assertInstanceOf(Cache::class, $connection->getCache());
        });

        tap(Container::getConnection('bravo'), function (Connection $connection) {
            $config = $connection->getConfiguration();

            $this->assertEquals(['172.0.0.1', '172.0.0.2'], $config->get('hosts'));
            $this->assertEquals('dc=bravo,dc=com', $config->get('base_dn'));
            $this->assertEquals('cn=user,dc=bravo,dc=com', $config->get('username'));
            $this->assertEquals('bravo-secret', $config->get('password'));

            $this->assertNull($connection->getCache());
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
}
