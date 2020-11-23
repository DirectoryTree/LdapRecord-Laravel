<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Query\Collection as LdapCollection;
use Illuminate\Support\Collection as LaravelCollection;

class BulkImportCompleted
{
    /**
     * The LDAP objects imported.
     *
     * @var LdapCollection
     */
    public $objects;

    /**
     * The Eloquent models of the LDAP objects imported.
     *
     * @var LaravelCollection
     */
    public $imported;

    /**
     * Constructor.
     *
     * @param LdapCollection    $objects
     * @param LaravelCollection $imported
     */
    public function __construct(LdapCollection $objects, LaravelCollection $imported)
    {
        $this->objects = $objects;
        $this->imported = $imported;
    }
}
