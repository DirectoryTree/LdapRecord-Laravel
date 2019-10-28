<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;

class Imported
{
    /**
     * The LDAP user that was successfully imported.
     *
     * @var LdapModel
     */
    public $user;

    /**
     * The model belonging to the user that was imported.
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
