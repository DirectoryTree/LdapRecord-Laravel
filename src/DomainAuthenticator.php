<?php

namespace LdapRecord\Laravel;

use LdapRecord\Auth\Guard;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;
use LdapRecord\Models\Model;

class DomainAuthenticator
{
    /**
     * The LDAP auth guard.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Constructor.
     *
     * @param Guard $auth
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Attempt authenticating against the domain.
     *
     * @param Model  $user
     * @param string $password
     *
     * @return bool
     */
    public function attempt(Model $user, $password)
    {
        event(new Authenticating($user, $user->getDn()));

        if ($this->auth->attempt($user->getDn(), $password)) {
            event(new Authenticated($user));

            return true;
        }

        event(new AuthenticationFailed($user));

        return false;
    }
}
