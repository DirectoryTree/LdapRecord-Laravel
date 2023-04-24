<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Collection;
use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model as LdapRecord;

class DeletedMissing implements LoggableEvent
{
    use Loggable;

    /**
     * The LdapRecord model used to import.
     */
    public LdapRecord $ldapModel;

    /**
     * The Eloquent model used to import.
     */
    public Eloquent $eloquentModel;

    /**
     * The Object GUIDs that have been deleted.
     */
    public Collection $deleted;

    /**
     * Constructor.
     */
    public function __construct(LdapRecord $ldapModel, Eloquent $eloquentModel, Collection $deleted)
    {
        $this->ldapModel = $ldapModel;
        $this->eloquentModel = $eloquentModel;
        $this->deleted = $deleted;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        $guids = $this->deleted->values()->implode(', ');

        return "Users with guids [$guids] have been soft-deleted due to being missing from LDAP result.";
    }
}
