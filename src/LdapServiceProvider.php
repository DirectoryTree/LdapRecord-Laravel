<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Commands\BrowseLdapServer;
use LdapRecord\Laravel\Commands\GetRootDse;
use LdapRecord\Laravel\Commands\MakeLdapModel;
use LdapRecord\Laravel\Commands\MakeLdapRule;
use LdapRecord\Laravel\Commands\MakeLdapScope;
use LdapRecord\Laravel\Commands\TestLdapConnection;
use LdapRecord\Laravel\Events\LoggableEvent;
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
        $this->loadEnvironmentConnections();

        $this->registerLogging();
        $this->registerCommands();
        $this->registerConfiguration();
        $this->registerEventListeners();
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
            GetRootDse::class,
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
        if (! Config::get('ldap.logging', false)) {
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
            Config::get('ldap.default', 'default')
        );

        $this->registerConfiguredConnections();
    }

    /**
     * Register the connections that exist in the configuration file.
     *
     * @return void
     */
    protected function registerConfiguredConnections()
    {
        foreach (Config::get('ldap.connections', []) as $name => $config) {
            $connection = $this->makeConnection($config);

            if (Config::get('ldap.cache.enabled', false)) {
                $this->registerLdapCache($connection);
            }

            Container::addConnection($connection, $name);
        }
    }

    /**
     * Registers the LDAP event listeners.
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        Event::listen('LdapRecord\Laravel\Events\*', function ($eventName, array $events) {
            collect($events)->filter(function ($event) {
                return $event instanceof LoggableEvent && $event->shouldLogEvent();
            })->each(function (LoggableEvent $event) {
                Log::log($event->getLogLevel(), $event->getLogMessage());
            });
        });
    }

    /**
     * Register the connections that exist in the environment file.
     *
     * @return void
     */
    protected function loadEnvironmentConnections()
    {
        $connections = array_filter(
            array_map('trim', explode(',', env('LDAP_CONNECTIONS', '')))
        );

        if (empty($connections)) {
            return;
        }

        Config::set('ldap.logging', env('LDAP_LOGGING', false));
        Config::set('ldap.default', env('LDAP_CONNECTION', 'default'));
        Config::set('ldap.cache.enabled', env('LDAP_CACHE', false));
        Config::set('ldap.cache.driver', env('LDAP_CACHE_DRIVER', 'file'));

        foreach ($connections as $name) {
            Config::set("ldap.connections.$name", $this->makeConnectionConfigFromEnv($name));
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
                $cache->driver(Config::get('ldap.cache.driver'))
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
