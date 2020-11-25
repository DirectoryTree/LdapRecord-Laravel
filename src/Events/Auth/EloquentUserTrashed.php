<?php

namespace LdapRecord\Laravel\Events\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model;

class EloquentUserTrashed extends LoggableEvent
{
    /**
     * The LDAP model that belongs to the trashed Eloquent model.
     *
     * @var Model
     */
    public $ldap;

    /**
     * The LDAP users authenticatable model.
     *
     * @var Authenticatable
     */
    public $user;

    /**
     * Constructor.
     *
     * @param Model           $user
     * @param Authenticatable $model
     */
    public function __construct(Model $user, Authenticatable $model)
    {
        $this->ldap = $user;
        $this->user = $model;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "User [{$this->ldap->getName()}] was denied authentication because their model is soft-deleted.";
    }
}
