<?php

namespace LdapRecord\Laravel\Events;

use LdapRecord\Models\Model;

class AuthenticationFailed
{
    /**
     * The user that failed authentication.
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
