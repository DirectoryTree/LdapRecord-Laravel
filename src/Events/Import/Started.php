<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Query\Collection;

class Started implements LoggableEvent
{
    use Loggable;

    /**
     * The LDAP objects being imported.
     */
    public Collection $objects;

    /**
     * Constructor.
     */
    public function __construct($objects)
    {
        $this->objects = $objects;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        return "Starting import of [{$this->objects->count()}] LDAP objects.";
    }
}
