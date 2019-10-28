<?php

namespace LdapRecord\Laravel\Events;

use LdapRecord\Models\Model;

class Authenticated
{
    /**
     * The LDAP user that has successfully authenticated.
     *
     * @var Model
     */
    public $user;

    /**
     * Constructor.
     *
     * @param Model $user
     */
    public function __construct(Model $user)
    {
        $this->user = $user;
    }
}
