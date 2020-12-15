<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Commands\MakeLdapModel;
use LdapRecord\Laravel\Commands\MakeLdapRule;
use LdapRecord\Laravel\Commands\MakeLdapScope;
use LdapRecord\Laravel\Commands\BrowseLdapServer;
use LdapRecord\Laravel\Commands\TestLdapConnection;

class LdapServiceProvider extends ServiceProvider
{
    /**
     * Run service provider boot operations.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerLogging();
        $this->registerConfiguration();
        $this->registerCommands();
        $this->registerLdapConnections();
    }

    /**
     * Register the publishable LDAP configuration file.
     *
     * @return void
     */
    protected function registerConfiguration()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ldap.php' => config_path('ldap.php'),
            ]);
        }
    }

    /**
     * Register the LDAP artisan commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([
            MakeLdapRule::class,
            MakeLdapScope::class,
            MakeLdapModel::class,
            TestLdapConnection::class,
            BrowseLdapServer::class,
        ]);
    }

    /**
     * Register the LDAP operation logger.
     *
     * @return void
     */
    protected function registerLogging()
    {
        if (! config('ldap.logging', true)) {
            return;
        }

        if (! is_null($logger = Log::getFacadeRoot())) {
            Container::getInstance()->setLogger($logger);
        }
    }

    /**
     * Register the configured LDAP connections.
     *
     * @return void
     */
    protected function registerLdapConnections()
    {
        Container::setDefaultConnection(config('ldap.default', 'default'));

        foreach (config('ldap.connections', []) as $name => $config) {
            $connection = new Connection($config);

            if (config('ldap.cache.enabled', false)) {
                $this->registerLdapCache($connection);
            }

            Container::addConnection($connection, $name);
        }
    }

    /**
     * Register the LDAP cache store on the given connection.
     *
     * @param Connection $connection
     *
     * @return void
     */
    protected function registerLdapCache(Connection $connection)
    {
        if (! is_null($cache = Cache::getFacadeRoot())) {
            $connection->setCache(
                $cache->driver(config('ldap.cache.driver'))
            );
        }
    }
}
