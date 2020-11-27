<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Models\Model as LdapModel;
use Illuminate\Database\Eloquent\Model as Eloquent;

abstract class Event
{
    /**
     * The LDAP user object.
     *
     * @var LdapModel
     */
    public $object;

    /**
     * The LDAP users authenticatable Eloquent model.
     *
     * @var Eloquent|null
     */
    public $eloquent;

    /**
     * Constructor.
     *
     * @param LdapModel          $object
     * @param Eloquent|null $user
     */
    public function __construct(LdapModel $object, Eloquent $user = null)
    {
        $this->object = $object;
        $this->eloquent = $user;
    }
}
