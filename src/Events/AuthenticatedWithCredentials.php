<?php

namespace LdapRecord\Laravel\Events;

use LdapRecord\Models\Model;
use Illuminate\Contracts\Auth\Authenticatable;

class AuthenticatedWithCredentials
{
    /**
     * The authenticated LDAP user.
     *
     * @var Model
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
     * @param Model                $user
     * @param Authenticatable|null $model
     */
    public function __construct(Model $user, Authenticatable $model = null)
    {
        $this->user = $user;
        $this->model = $model;
    }
}
