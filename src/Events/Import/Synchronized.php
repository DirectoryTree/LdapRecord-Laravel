<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model as LdapModel;

class Synchronized extends LoggableEvent
{
    /**
     * The LDAP object that was synchronized.
     *
     * @var LdapModel
     */
    public $object;

    /**
     * The LDAP objects database model that was synchronized.
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

