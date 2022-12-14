<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Support\Facades\Config;

trait Loggable
{
    /**
     * Get the level of log message (i.e. info, alert, critical).
     *
     * @return string
     */
    public function getLogLevel()
    {
        return 'info';
    }

    /**
     * Determine if event should be logged.
     *
     * @return bool
     */
    public function shouldLogEvent()
    {
        return Config::get('ldap.logging', false);
    }
}
