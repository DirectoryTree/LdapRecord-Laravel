<?php

namespace LdapRecord\Laravel\Events;

use LdapRecord\Models\Model;

class DiscoveredWithCredentials
{
    /**
     * The discovered LDAP user before authentication.
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
