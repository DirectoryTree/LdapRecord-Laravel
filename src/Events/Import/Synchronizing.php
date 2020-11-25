<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Models\Model as LdapModel;
use LdapRecord\Laravel\Events\LoggableEvent;

class Synchronizing extends LoggableEvent
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

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "Object with name [{$this->object->getName()}] has been successfully synchronized.";
    }
}
