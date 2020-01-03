<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Model as LdapModel;

class AuthenticationRejected
{
    /**
     * The user that has been denied authentication.
     *
     * @var LdapModel
     */
    public $user;

    /**
     * The LDAP users authenticatable model.
     *
     * @var Authenticatable|null
     */
    public $model;

    /**
     * Constructor.
     *
     * @param LdapModel            $user
     * @param Authenticatable|null $model
     */
    public function __construct(LdapModel $user, Authenticatable $model = null)
    {
        $this->user = $user;
        $this->model = $model;
    }
}
