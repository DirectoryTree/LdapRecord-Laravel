<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

abstract class Event
{
    /**
     * The LDAP object.
     *
     * @var LdapModel
     */
    public $object;

    /**
     * The Eloquent model belonging to the LDAP object.
     *
     * @var EloquentModel
     */
    public $eloquent;

    /**
     * Constructor.
     *
     * @param LdapModel     $object
     * @param EloquentModel $eloquent
     */
    public function __construct(LdapModel $object, EloquentModel $eloquent)
    {
        $this->object = $object;
        $this->eloquent = $eloquent;
    }
}
