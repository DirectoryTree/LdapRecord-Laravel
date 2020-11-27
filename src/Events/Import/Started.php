<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Query\Collection;
use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;

class Started implements LoggableEvent
{
    use Loggable;

    /**
     * The LDAP objects being imported.
     *
     * @var Collection
     */
    public $objects;

    /**
     * Constructor.
     *
     * @param $objects
     */
    public function __construct($objects)
    {
        $this->objects = $objects;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "Starting import of [{$this->objects->count()}] LDAP objects.";
    }
}
