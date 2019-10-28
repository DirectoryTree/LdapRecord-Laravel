<?php

namespace LdapRecord\Laravel\Events;

use LdapRecord\Models\Model;

class Authenticating
{
    /**
     * The LDAP user that is authenticating.
     *
     * @var Model
     */
    public $user;

    /**
     * The username being used for authentication.
     *
     * @var string
     */
    public $username = '';

    /**
     * Constructor.
     *
     * @param Model  $user
     * @param string $username
     */
    public function __construct(Model $user, $username = '')
    {
        $this->user = $user;
        $this->username = $username;
    }
}
