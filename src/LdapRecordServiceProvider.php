<?php

namespace LdapRecord\Laravel;

use LdapRecord\Container;
use LdapRecord\Connection;
use Illuminate\Support\Str;
use LdapRecord\LdapRecordException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;

class LdapRecordServiceProvider extends ServiceProvider
{
    /**
     * Run service provider boot operations.
     *
     * @return void
     */
    public function boot()
    {
        if ($this->isLogging()) {
            Container::setLogger(logger());
        }

        if ($this->isLumen()) {
            return;
        }

        if ($this->app->runningInConsole()) {
            $config = __DIR__.'/Config/config.php';

            $this->publishes([
                $config => config_path('ldap.php'),
            ]);
        }
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $container = Container::getInstance();

        foreach (config('ldap.connections', []) as $name => $config) {
            $connection = new Connection($config['settings']);

            $container->add($connection, $name);

            // If auto connect is enabled, an attempt will be made to bind to
            // the LDAP server with the configured credentials. If this
            // fails then the exception will be logged (if enabled).
            if ($this->shouldAutoConnect($config)) {
                try {
                    $connection->connect();
                } catch (LdapRecordException $e) {
                    if ($this->isLogging()) {
                        logger()->error($e);
                    }
                }
            }
        }
    }

    /**
     * Determines if the given settings has auto connect enabled.
     *
     * @param array $settings
     *
     * @return bool
     */
    protected function shouldAutoConnect(array $settings)
    {
        return array_key_exists('auto_connect', $settings)
            && $settings['auto_connect'] === true;
    }

    /**
     * Determines whether logging is enabled.
     *
     * @return bool
     */
    protected function isLogging()
    {
        return Config::get('ldap.logging', false);
    }

    /**
     * Determines if the current application is a Lumen instance.
     *
     * @return bool
     */
    protected function isLumen()
    {
        return Str::contains($this->app->version(), 'Lumen');
    }
}
