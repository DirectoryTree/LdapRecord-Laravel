<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Query\Collection;

class BulkImportStarted
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
}
