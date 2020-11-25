<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Models\Model;
use LdapRecord\Laravel\Events\LoggableEvent;
use Illuminate\Contracts\Auth\Authenticatable;

class CompletedWithWindows extends LoggableEvent
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
     * @var Authenticatable|null
     */
    public $model = null;

    /**
     * Constructor.
     *
     * @param Model           $user
     * @param Authenticatable $model
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
        return "User [{$this->user->getName()}] has successfully authenticated via NTLM.";
    }
}
