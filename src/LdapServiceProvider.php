<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\ServiceProvider;
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
        if (DomainRegistrar::$logging) {
            Container::setLogger(logger());
        }

        DomainRegistrar::setup();
    }
}
