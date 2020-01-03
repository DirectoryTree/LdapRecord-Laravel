<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\ServiceProvider;
use LdapRecord\Connection;
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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ldap.php' => config_path('ldap.php'),
            ]);
        }

        if (config('ldap.logging', true)) {
            Container::setLogger(logger());
        }

        foreach (config('ldap.connections', []) as $name => $config) {
            Container::addConnection(new Connection($config), $name);
        }
    }
}
