<?php

namespace LdapRecord\Laravel\Events\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Models\Model;

class EloquentUserTrashed
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
}
