<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Models\Model as LdapModel;
use LdapRecord\Laravel\Events\LoggableEvent;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Imported extends LoggableEvent
{
    /**
     * The successfully imported LDAP objects model.
     *
     * @var LdapModel
     */
    public $ldap;

    /**
     * The successfully imported LDAP objects eloquent model.
     *
     * @var Eloquent
     */
    public $eloquent;

    /**
     * Constructor.
     *
     * @param LdapModel $ldap
     * @param Eloquent  $eloquent
     */
    public function __construct(LdapModel $ldap, Eloquent $eloquent)
    {
        $this->ldap = $ldap;
        $this->eloquent = $eloquent;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "Object with name [{$this->ldap->getName()}] has been successfully imported.";
    }
}
