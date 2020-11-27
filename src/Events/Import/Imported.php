<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;

class Imported extends Event implements LoggableEvent
{
    use Loggable;

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "Object with name [{$this->object->getName()}] has been successfully imported.";
    }
}
