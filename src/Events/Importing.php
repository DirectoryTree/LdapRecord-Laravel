<?php

namespace LdapRecord\Laravel\Events;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;

class Importing
{
    /**
     * The user being imported.
     *
     * @var LdapModel
     */
    public $user;

    /**
     * The model belonging to the user being imported.
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
