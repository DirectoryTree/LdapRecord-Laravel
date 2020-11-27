<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Models\Model as LdapModel;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Restored
{
    /**
     * The successfully restored LDAP objects model.
     *
     * @var LdapModel
     */
    public $ldap;

    /**
     * The successfully restored LDAP objects eloquent model.
     *
     * @var Eloquent
     */
    public $eloquent;

    /**
     * Constructor.
     *
     * @param LdapModel $ldap
     * @param Eloquent  $eloquent
     */
    public function __construct(LdapModel $ldap, Eloquent $eloquent)
    {
        $this->ldap = $ldap;
        $this->eloquent = $eloquent;
    }
}
