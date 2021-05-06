<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;

class Binding extends Event implements LoggableEvent
{
    use Loggable;

    /**
     * @inheritdoc
     */
    public function getLogMessage()
    {
        return "User [{$this->object->getName()}] is authenticating.";
    }
}
