<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;

abstract class Rule
{
    /**
     * The LDAP user.
     *
     * @var LdapModel
     */
    protected $user;

    /**
     * The Eloquent model.
     *
     * @var Model|null
     */
    protected $model;

    /**
     * Constructor.
     *
     * @param LdapModel  $user
     * @param Model|null $model
     */
    public function __construct(LdapModel $user, Model $model = null)
    {
        $this->user = $user;
        $this->model = $model;
    }

    /**
     * Check if the rule passes validation.
     *
     * @return bool
     */
    abstract public function isValid();
}
