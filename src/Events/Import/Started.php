<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Query\Collection;
use LdapRecord\Laravel\Events\LoggableEvent;

class Started extends LoggableEvent
{
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
