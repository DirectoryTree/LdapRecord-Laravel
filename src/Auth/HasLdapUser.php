<?php

namespace LdapRecord\Laravel\Auth;

use LdapRecord\Models\Model;

trait HasLdapUser
{
    /**
     * The LDAP User that is bound to the current model.
     *
     * @var Model|null
     */
    public $ldap;

    /**
     * Sets the LDAP user that is bound to the model.
     *
     * @param Model $user
     */
    public function setLdapUser(Model $user = null)
    {
        $this->ldap = $user;
    }
}
