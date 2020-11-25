<?php

namespace LdapRecord\Laravel\Events\Ldap;

use LdapRecord\Models\Model;
use LdapRecord\Laravel\Events\LoggableEvent;

class Binding extends LoggableEvent
{
    /**
     * The LDAP user that is authenticating.
     *
     * @var Model
     */
    public $user;

    /**
     * The username being used for authentication.
     *
     * @var string
     */
    public $username = '';

    /**
     * Constructor.
     *
     * @param Model  $user
     * @param string $username
     */
    public function __construct(Model $user, $username = '')
    {
        $this->user = $user;
        $this->username = $username;
    }

    /**
     * {@inheritDoc}
     */
    public function getLogMessage()
    {
        return "User [{$this->user->getName()}] is authenticating.";
    }
}
