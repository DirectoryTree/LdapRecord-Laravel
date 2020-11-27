<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;

class BindFailed extends Event implements LoggableEvent
{
    use Loggable;

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "User [{$this->eloquent->getName()}] has failed LDAP authentication.";
    }
}
