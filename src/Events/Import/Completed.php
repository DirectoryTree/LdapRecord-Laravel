<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection as LaravelCollection;
use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Query\Collection as LdapCollection;

class Completed implements LoggableEvent
{
    use Loggable;

    /**
     * The LDAP objects imported.
     */
    public LdapCollection $objects;

    /**
     * The Eloquent models of the LDAP objects imported.
     */
    public LaravelCollection $imported;

    /**
     * Constructor.
     */
    public function __construct(LdapCollection $objects, LaravelCollection $imported)
    {
        $this->objects = $objects;
        $this->imported = $imported;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        $imported = $this->imported->filter(function (Model $eloquent) {
            return $eloquent->wasRecentlyCreated;
        })->count();

        $synchronized = $this->imported->count() - $imported;

        return "Completed import. Imported [$imported] new LDAP objects. Synchronized [$synchronized] existing LDAP objects.";
    }
}
