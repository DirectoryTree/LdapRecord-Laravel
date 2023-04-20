<?php

namespace LdapRecord\Laravel\Events;

interface LoggableEvent
{
    /**
     * Get the message to log.
     *
     * @return string
     */
    public function getLogMessage();

    /**
     * Get the level of log message (i.e. info, alert, critical).
     */
    public function getLogLevel();

    /**
     * Determine if event should be logged.
     *
     * @return bool
     */
    public function shouldLogEvent();
}
