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
     *
     * @var LdapRecord
     */
    public $ldapModel;

    /**
     * The Eloquent model used to import.
     *
     * @var Eloquent
     */
    public $eloquentModel;

    /**
     * The Object GUIDs that have been deleted.
     *
     * @var Collection
     */
    public $deleted;

    /**
     * Constructor.
     *
     * @param LdapRecord $ldapModel
     * @param Eloquent   $eloquentModel
     * @param Collection $deleted
     */
    public function __construct(LdapRecord $ldapModel, Eloquent $eloquentModel, Collection $deleted)
    {
        $this->ldapModel = $ldapModel;
        $this->eloquentModel = $eloquentModel;
        $this->deleted = $deleted;
    }

    /**
     * @inheritdoc
     */
    public function getLogMessage()
    {
        $guids = $this->deleted->values()->implode(', ');

        return "Users with guids [$guids] have been soft-deleted due to being missing from LDAP result.";
    }
}
