<?php

namespace LdapRecord\Laravel;

use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;
use LdapRecord\Models\Model;

class DomainAuthenticator
{
    /**
     * The LDAP domain being used for authentication.
     *
     * @var Domain
     */
    protected $domain;

    /**
     * Constructor.
     *
     * @param Domain $domain
     */
    public function __construct(Domain $domain)
    {
        $this->domain = $domain;
    }

    /**
     * Attempt authenticating against the domain.
     *
     * @param Model  $user
     * @param string $password
     *
     * @return bool
     */
    public function attempt(Model $user, string $password) : bool
    {
        event(new Authenticating($user, $user->getDn()));

        if ($this->getDomainGuard()->attempt($user->getDn(), $password)) {
            event(new Authenticated($user));

            return true;
        }

        event(new AuthenticationFailed($user));

        return false;
    }

    /**
     * Get the domain connection guard.
     *
     * @return \LdapRecord\Auth\Guard
     */
    protected function getDomainGuard()
    {
        return $this->domain->getConnection()->auth();
    }
}
