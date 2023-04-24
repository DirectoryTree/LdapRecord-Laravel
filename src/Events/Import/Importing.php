<?php

namespace LdapRecord\Laravel\Events\Import;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;

class Importing extends Event implements LoggableEvent
{
    use Loggable;

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        return "Object with name [{$this->object->getName()}] is being imported.";
    }
}
