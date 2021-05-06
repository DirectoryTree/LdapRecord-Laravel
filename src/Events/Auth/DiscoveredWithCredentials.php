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
     *
     * @var Model
     */
    public $user;

    /**
     * Constructor.
     *
     * @param Model $user
     */
    public function __construct(Model $user)
    {
        $this->user = $user;
    }

    /**
     * @inheritdoc
     */
    public function getLogMessage()
    {
        return "User [{$this->user->getName()}] has been successfully discovered for authentication.";
    }
}
