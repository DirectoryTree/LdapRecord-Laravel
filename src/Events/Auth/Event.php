<?php

namespace LdapRecord\Laravel\Events\Auth;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

abstract class Event
{
    /**
     * The LDAP user object.
     */
    public LdapModel $object;

    /**
     * The LDAP users authenticatable Eloquent model.
     */
    public ?EloquentModel $eloquent;

    /**
     * Constructor.
     */
    public function __construct(LdapModel $object, ?EloquentModel $eloquent = null)
    {
        $this->object = $object;
        $this->eloquent = $eloquent;
    }
}
