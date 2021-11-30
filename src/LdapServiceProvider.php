<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Commands\BrowseLdapServer;
use LdapRecord\Laravel\Commands\MakeLdapModel;
use LdapRecord\Laravel\Commands\MakeLdapRule;
use LdapRecord\Laravel\Commands\MakeLdapScope;
use LdapRecord\Laravel\Commands\TestLdapConnection;
use LdapRecord\Laravel\Testing\LdapDatabaseManager;

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
        $this->registerCommands();
        $this->registerConfiguration();
        $this->registerLdapConnections();

        if ($this->app->runningUnitTests()) {
            $this->app->singleton(LdapDatabaseManager::class);
        }
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
            ], 'ldap-config');
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
            BrowseLdapServer::class,
            TestLdapConnection::class,
        ]);
    }

    /**
     * Register the LDAP operation logger.
     *
     * @return void
     */
    protected function registerLogging()
    {
        if (! config('ldap.logging', env('LDAP_LOGGING', true))) {
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
            config('ldap.default', env('LDAP_CONNECTION', 'default'))
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
            $connection = $this->makeConnection($config);

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
        $connections = array_filter(
            array_map('trim', explode(',', env('LDAP_CONNECTIONS', '')))
        );

        foreach ($connections as $name) {
            $connection = $this->makeConnection(
                $this->makeConnectionConfigFromEnv($name)
            );

            if (env($this->makeEnvVariable('LDAP_{name}_CACHE', $name), false)) {
                $this->registerLdapCache($connection);
            }

            Container::addConnection($connection, $name);
        }
    }

    /**
     * Make a new LDAP connection.
     *
     * @param array $config
     *
     * @return Connection
     */
    protected function makeConnection($config)
    {
        return app(Connection::class, ['config' => $config]);
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
     * @param string $connection
     *
     * @return array
     */
    protected function makeConnectionConfigFromEnv($connection)
    {
        return array_filter([
            'hosts' => explode(',', env($this->makeEnvVariable('LDAP_{name}_HOSTS', $connection))),
            'username' => env($this->makeEnvVariable('LDAP_{name}_USERNAME', $connection)),
            'password' => env($this->makeEnvVariable('LDAP_{name}_PASSWORD', $connection)),
            'port' => env($this->makeEnvVariable('LDAP_{name}_PORT', $connection), 389),
            'base_dn' => env($this->makeEnvVariable('LDAP_{name}_BASE_DN', $connection)),
            'timeout' => env($this->makeEnvVariable('LDAP_{name}_TIMEOUT', $connection), 5),
            'use_ssl' => env($this->makeEnvVariable('LDAP_{name}_SSL', $connection), false),
            'use_tls' => env($this->makeEnvVariable('LDAP_{name}_TLS', $connection), false),
            'options' => $this->makeCustomOptionsFromEnv($connection),
        ]);
    }

    /**
     * Make a connection's custom config options array from the env.
     *
     * @param string $connection
     *
     * @return array
     */
    protected function makeCustomOptionsFromEnv($connection)
    {
        $constant = $this->makeEnvVariable('LDAP_{name}_OPT', $connection);

        return collect($_ENV)
            // First, we will capture our entire applications ENV and
            // fetch all variables that contain the constant name
            // pattern that matches, includes the connection.
            ->filter(function ($value, $key) use ($constant) {
                return strpos($key, $constant) !== false;
            })
            // Finally, with the ENV variables that have been fetched for
            // the connection, subsitute the connection's name to make
            // the literal PHP constant the developer is expecting.
            ->mapWithKeys(function ($value, $key) use ($connection) {
                $replace = $this->makeEnvVariable('_{name}_', $connection);

                $option = str_replace($replace, '_', $key);

                return [constant($option) => $value];
            })->toArray();
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
