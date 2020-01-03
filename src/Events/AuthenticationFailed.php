<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Contracts\Auth\Authenticatable;
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
     * The LDAP users authenticatable model.
     *
     * @var Authenticatable|null
     */
    public $model;

    /**
     * Constructor.
     *
     * @param Model                $user
     * @param Authenticatable|null $model
     */
    public function __construct(Model $user, Authenticatable $model = null)
    {
        $this->user = $user;
        $this->model = $model;
    }
}
