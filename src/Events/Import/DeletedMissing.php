<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Support\Collection;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model as LdapRecord;
use Illuminate\Database\Eloquent\Model as Eloquent;

class DeletedMissing extends LoggableEvent
{
    /**
     * The LdapRecord model used to import.
     *
     * @var LdapRecord
     */
    public $ldap;

    /**
     * The Eloquent model used to import.
     *
     * @var Eloquent
     */
    public $eloquent;

    /**
     * The Object GUIDs that have been deleted.
     *
     * @var Collection
     */
    public $deleted;

    /**
     * Constructor.
     *
     * @param LdapRecord $ldap
     * @param Eloquent   $eloquent
     * @param Collection $deleted
     */
    public function __construct(LdapRecord $ldap, Eloquent $eloquent, Collection $deleted)
    {
        $this->ldap = $ldap;
        $this->eloquent = $eloquent;
        $this->deleted = $deleted;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        $guids = $this->deleted->values()->implode(', ');

        return "Users with guids [$guids] have been soft-deleted due to being missing from LDAP result.";
    }
}
