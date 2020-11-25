<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model as LdapModel;
use Illuminate\Contracts\Auth\Authenticatable;

class Rejected extends LoggableEvent
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
     * @param LdapModel            $ldap
     * @param Authenticatable|null $user
     */
    public function __construct(LdapModel $ldap, Authenticatable $user = null)
    {
        $this->ldap = $ldap;
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "User [{$this->ldap->getName()}] has failed validation. They have been denied authentication.";
    }
}
