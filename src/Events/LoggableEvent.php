<?php

namespace LdapRecord\Laravel\Events;

abstract class LoggableEvent
{
    /**
     * Get the message to log.
     *
     * @return string
     */
    abstract public function getLogMessage();

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
