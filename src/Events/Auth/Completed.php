<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Models\Model as LdapModel;
use Illuminate\Contracts\Auth\Authenticatable;

class Completed
{
    /**
     * The LDAP model that belongs to the authenticatable model.
     *
     * @var LdapModel
     */
    public $ldap;

    /**
     * The LDAP users authenticatable model.
     *
     * @var Authenticatable
     */
    public $user;

    /**
     * Constructor.
     *
     * @param LdapModel       $ldap
     * @param Authenticatable $user
     */
    public function __construct(LdapModel $ldap, Authenticatable $user)
    {
        $this->ldap = $ldap;
        $this->user = $user;
    }
}
