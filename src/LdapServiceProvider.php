<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use LdapRecord\Container;

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

        app(DomainRegistrar::class)->setup();

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
        return collect(config('ldap.domains', []))->transform(function ($domain, $name) {
            return new $domain($name);
        })->toArray();
    }

    /**
     * Determine whether LDAP logging is enabled.
     *
     * @return bool
     */
    protected function isLogging()
    {
        return config('ldap.logging.enabled', false);
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
