<?php

namespace LdapRecord\Laravel\Events\Import;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

abstract class Event
{
    /**
     * The LDAP object.
     */
    public LdapModel $object;

    /**
     * The Eloquent model belonging to the LDAP object.
     */
    public EloquentModel $eloquent;

    /**
     * Constructor.
     */
    public function __construct(LdapModel $object, EloquentModel $eloquent)
    {
        $this->object = $object;
        $this->eloquent = $eloquent;
    }
}
