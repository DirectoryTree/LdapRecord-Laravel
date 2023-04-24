<?php

namespace LdapRecord\Laravel\Events\Auth;

use LdapRecord\Laravel\Events\Loggable;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Models\Model;

class DiscoveredWithCredentials implements LoggableEvent
{
    use Loggable;

    /**
     * The discovered LDAP user before authentication.
     */
    public Model $user;

    /**
     * Constructor.
     */
    public function __construct(Model $user)
    {
        $this->user = $user;
    }

    /**
     * {@inheritdoc}
     */
    public function getLogMessage(): string
    {
        return "User [{$this->user->getName()}] has been successfully discovered for authentication.";
    }
}
