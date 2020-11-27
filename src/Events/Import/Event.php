<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Models\Model as LdapModel;
use Illuminate\Database\Eloquent\Model as Eloquent;

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
     * @var Eloquent
     */
    public $eloquent;

    /**
     * Constructor.
     *
     * @param LdapModel $object
     * @param Eloquent  $eloquent
     */
    public function __construct(LdapModel $object, Eloquent $eloquent)
    {
        $this->object = $object;
        $this->eloquent = $eloquent;
    }
}
