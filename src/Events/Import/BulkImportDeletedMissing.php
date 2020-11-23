<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Support\Collection;
use LdapRecord\Models\Model as LdapRecord;
use Illuminate\Database\Eloquent\Model as Eloquent;

class BulkImportDeletedMissing
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
}
