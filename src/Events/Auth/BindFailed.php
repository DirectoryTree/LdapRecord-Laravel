<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;

class BindFailed extends Event implements LoggableEvent
{
    use Loggable;

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        return "User [{$this->object->getName()}] has failed LDAP authentication.";
    }
}
