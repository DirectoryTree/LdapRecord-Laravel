<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;

class Synchronizing
{
    /**
     * The object being synchronized.
     *
     * @var LdapModel
     */
    public $object;

    /**
     * The model belonging to the object being synchronized.
     *
     * @var Model
     */
    public $model;

    /**
     * Constructor.
     *
     * @param LdapModel $object
     * @param Model     $model
     */
    public function __construct(LdapModel $object, Model $model)
    {
        $this->object = $object;
        $this->model = $model;
    }
}
