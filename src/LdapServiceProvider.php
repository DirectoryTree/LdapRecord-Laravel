<?php

namespace LdapRecord\Laravel;

use LdapRecord\Container;
use LdapRecord\Connection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Laravel\Commands\MakeLdapRule;
use LdapRecord\Laravel\Commands\MakeLdapModel;
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
     * Register the application's LDAP connections.
     *
     * @return void
     */
    protected function registerLdapConnections()
    {
        Container::setDefaultConnection(
            config('ldap.default', env('LDAP_CONNECTION'))
        );

        $this->registerConfiguredConnections();
        $this->registerEnvironmentConnections();
    }

    /**
     * Register the connections that exist in the configuration file.
     *
     * @return void
     */
    protected function registerConfiguredConnections()
    {
        foreach (config('ldap.connections', []) as $name => $config) {
            $connection = new Connection($config);

            if (config('ldap.cache.enabled', false)) {
                $this->registerLdapCache($connection);
            }

            Container::addConnection($connection, $name);
        }
    }

    /**
     * Register the connections that exist in the environment file.
     *
     * @return void
     */
    protected function registerEnvironmentConnections()
    {
        $connections = array_filter(explode(',', env('LDAP_CONNECTIONS')));

        foreach ($connections as $name) {
            $connection = new Connection(
                $this->makeConnectionConfigFromEnv($name)
            );

            if (env($this->makeEnvVariable('LDAP_{name}_CACHE', $name), false)) {
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

    /**
     * Make a connection's configuration from the connection's environment name.
     *
     * @param string $name
     * 
     * @return array
     */
    protected function makeConnectionConfigFromEnv($name)
    {
        return array_filter([
            'hosts' => explode(',', env($this->makeEnvVariable('LDAP_{name}_HOSTS', $name))),
            'username' => env($this->makeEnvVariable('LDAP_{name}_USERNAME', $name)),
            'password' => env($this->makeEnvVariable('LDAP_{name}_PASSWORD', $name)),
            'port' => env($this->makeEnvVariable('LDAP_{name}_PORT', $name), 389),
            'base_dn' => env($this->makeEnvVariable('LDAP_{name}_BASE_DN', $name)),
            'timeout' => env($this->makeEnvVariable('LDAP_{name}_TIMEOUT', $name), 5),
            'use_ssl' => env($this->makeEnvVariable('LDAP_{name}_SSL', $name), false),
            'use_tls' => env($this->makeEnvVariable('LDAP_{name}_TLS', $name), false),
        ]);
    }

    /**
     * Substitute the env name template with the given connection name.
     *
     * @param string $env
     * @param string $name
     * 
     * @return string
     */
    protected function makeEnvVariable($env, $name)
    {
        return str_replace('{name}', strtoupper($name), $env);
    }
}
