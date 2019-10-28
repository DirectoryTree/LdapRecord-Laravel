<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;

class Synchronized
{
    /**
     * The LDAP user that was synchronized.
     *
     * @var LdapModel
     */
    public $user;

    /**
     * The LDAP users database model that was synchronized.
     *
     * @var Model
     */
    public $model;

    /**
     * Constructor.
     *
     * @param LdapModel $user
     * @param Model     $model
     */
    public function __construct(LdapModel $user, Model $model)
    {
        $this->user = $user;
        $this->model = $model;
    }
}
