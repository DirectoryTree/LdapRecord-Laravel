<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Support\Facades\Config;

trait Loggable
{
    /**
     * Get the level of log message (i.e. info, alert, critical).
     */
    public function getLogLevel(): string
    {
        return 'info';
    }

    /**
     * Determine if event should be logged.
     */
    public function shouldLogEvent(): bool
    {
        return Config::get('ldap.logging.enabled', false);
    }
}
