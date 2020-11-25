<?php

namespace LdapRecord\Laravel\Events\Ldap;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model;

class BindFailed extends LoggableEvent
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

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "User [{$this->user->getName()}] has failed LDAP authentication.";
    }
}
