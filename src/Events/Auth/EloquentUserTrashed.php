<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;

class EloquentUserTrashed extends Event implements LoggableEvent
{
    use Loggable;

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        return "User [{$this->object->getName()}] was denied authentication because their model is soft-deleted.";
    }
}
