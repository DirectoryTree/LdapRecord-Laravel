<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;

abstract class Rule
{
    /**
     * The LDAP user.
     */
    protected LdapModel $user;

    /**
     * The Eloquent model.
     */
    protected ?Model $model;

    /**
     * Constructor.
     */
    public function __construct(LdapModel $user, Model $model = null)
    {
        $this->user = $user;
        $this->model = $model;
    }

    /**
     * Check if the rule passes validation.
     */
    abstract public function isValid(): bool;
}
