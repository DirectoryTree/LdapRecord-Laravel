<?php

namespace LdapRecord\Laravel;

use LdapRecord\Container;
use Illuminate\Support\Str;
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

        app(DomainRegistrar::class)->setup();
    }

    /**
     * Register the configured LDAP domains.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(DomainRegistrar::class, function () {
            return new DomainRegistrar($this->getDomains());
        });
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
