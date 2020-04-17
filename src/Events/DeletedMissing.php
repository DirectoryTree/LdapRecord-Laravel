<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class DeletedMissing
{
    /**
     * A collection of soft-deleted user IDs that were missing from import.
     *
     * @var \Illuminate\Support\Collection
     */
    public $ids;

    /**
     * The LDAP model that was used for the import.
     *
     * @var LdapModel
     */
    public $ldap;

    /**
     * The Eloquent model that was used for the import.
     *
     * @var EloquentModel
     */
    public $eloquent;

    /**
     * Constructor.
     *
     * @param \Illuminate\Support\Collection $ids
     * @param LdapModel                      $ldap
     * @param EloquentModel                  $eloquent
     */
    public function __construct($ids, LdapModel $ldap, EloquentModel $eloquent)
    {
        $this->ids = $ids;
        $this->ldap = $ldap;
        $this->eloquent = $eloquent;
    }
}
