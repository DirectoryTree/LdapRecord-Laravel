<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\Arr;
use LdapRecord\Models\Model;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;

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
     * @param Model $user
     * @param array $credentials
     *
     * @return bool
     *
     * @throws \LdapRecord\Auth\BindException
     */
    public function attempt(Model $user, array $credentials = [])
    {
        $username = $user->getDn();
        $password = $this->getPasswordFromCredentials($credentials);

        event(new Authenticating($user, $username));

        if ($this->getDomainGuard()->attempt($username, $password)) {
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

    /**
     * Get the password
     *
     * @param array $credentials
     *
     * @return string|null
     */
    protected function getPasswordFromCredentials(array $credentials = [])
    {
        return Arr::get($credentials, 'password');
    }
}
