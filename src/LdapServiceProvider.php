<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\ServiceProvider;
use LdapRecord\Connection;
use LdapRecord\Container;
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
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/ldap.php' => config_path('ldap.php'),
            ]);
        }

        $this->commands([
            MakeLdapRule::class,
            MakeLdapScope::class,
            MakeLdapModel::class,
            TestLdapConnection::class,
        ]);

        if (config('ldap.logging', true)) {
            Container::setLogger(logger());
        }

        Container::setDefaultConnection(config('ldap.default', 'default'));

        foreach (config('ldap.connections', []) as $name => $config) {
            Container::addConnection(new Connection($config), $name);
        }
    }
}
