<?php

namespace LdapRecord\Laravel\Events;

trait Loggable
{
    /**
     * Get the level of log message (i.e. info, alert, critical).
     *
     * @return mixed
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
        return config('ldap.logging', false);
    }
}
