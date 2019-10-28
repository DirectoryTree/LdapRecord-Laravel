<?php

namespace LdapRecord\Laravel\Events;

use LdapRecord\Models\Model as LdapModel;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthenticationSuccessful
{
    /**
     * The LDAP user that has successfully authenticated.
     *
     * @var LdapModel
     */
    public $user;

    /**
     * The LDAP users authenticatable model.
     *
     * @var Authenticatable
     */
    public $model;

    /**
     * Constructor.
     *
     * @param LdapModel       $user
     * @param Authenticatable $model
     */
    public function __construct(LdapModel $user, Authenticatable $model)
    {
        $this->user = $user;
        $this->model = $model;
    }
}
