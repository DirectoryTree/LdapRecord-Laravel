<?php

namespace LdapRecord\Laravel\Events;

interface LoggableEvent
{
    /**
     * Get the message to log.
     */
    public function getLogMessage(): string;

    /**
     * Get the level of log message (i.e. info, alert, critical).
     */
    public function getLogLevel(): string;

    /**
     * Determine if event should be logged.
     */
    public function shouldLogEvent(): bool;
}
