<?php

namespace LdapRecord\Laravel;

use LdapRecord\Container;
use Illuminate\Support\Str;
use LdapRecord\LdapRecordException;
use Illuminate\Support\ServiceProvider;

class LdapServiceProvider extends ServiceProvider
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
     * Register the configured LDAP domains.
     *
     * @return void
     */
    public function register()
    {
        foreach ($this->getDomains() as $domainClass) {
            /** @var \LdapRecord\Laravel\Domain $domain */
            $domain = app($domainClass);

            if ($domain->isAutoConnecting()) {
                try {
                    $domain->connect();
                } catch (LdapRecordException $ex) {
                    if ($this->isLogging()) {
                        logger()->error($ex->getMessage());
                    }
                }
            }

            $this->app->singleton($domainClass, $domain);
        }
    }

    /**
     * Get the configured LDAP domains.
     *
     * @return array
     */
    protected function getDomains()
    {
        return config('ldap.domains', []);
    }

    /**
     * Determine whether LDAP logging is enabled.
     *
     * @return bool
     */
    protected function isLogging()
    {
        return config('ldap.logging', false);
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
