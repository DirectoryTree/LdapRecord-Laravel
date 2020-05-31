<?php

namespace LdapRecord\Laravel;

use LdapRecord\Container;
use LdapRecord\Connection;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Laravel\Commands\MakeLdapModel;
use LdapRecord\Laravel\Commands\MakeLdapRule;
use LdapRecord\Laravel\Commands\MakeLdapScope;
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
        $this
            ->registerConfiguration()
            ->registerCommands()
            ->registerLogging()
            ->registerLdapConnections();
    }

    /**
     * Register the publishable LDAP configuration file.
     *
     * @return $this
     */
    protected function registerConfiguration()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ldap.php' => config_path('ldap.php'),
            ]);
        }

        return $this;
    }

    /**
     * Register the LDAP artisan commands.
     *
     * @return $this
     */
    protected function registerCommands()
    {
        $this->commands([
            MakeLdapRule::class,
            MakeLdapScope::class,
            MakeLdapModel::class,
            TestLdapConnection::class,
        ]);

        return $this;
    }

    /**
     * Register the LDAP operation logger.
     *
     * @return $this
     */
    protected function registerLogging()
    {
        if (config('ldap.logging', true)) {
            Container::getInstance()->setLogger(logger());
        }

        return $this;
    }

    /**
     * Register the configured LDAP connections.
     *
     * @return $this
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

        return $this;
    }

    /**
     * Register the LDAP cache store on the given connection.
     *
     * @param Connection $connection
     */
    protected function registerLdapCache(Connection $connection)
    {
        $connection->setCache(
            cache()->driver(config('ldap.cache.driver'))
        );
    }
}
