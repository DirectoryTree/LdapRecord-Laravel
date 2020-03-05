<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Log\LogManager;
use LdapRecord\Container;
use LdapRecord\Laravel\LdapServiceProvider;
use LdapRecord\Query\Cache;

class LdapServiceProviderTest extends TestCase
{
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

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
}
