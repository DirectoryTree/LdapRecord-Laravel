<?php

namespace LdapRecord\Laravel\Auth;

use LdapRecord\Laravel\Domain;
use Illuminate\Contracts\Auth\UserProvider as LaravelUserProvider;

abstract class UserProvider implements LaravelUserProvider
{
    /**
     * The LDAP domain to use for authentication.
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
     * Get the LDAP domain.
     *
     * @return Domain
     */
    public function getDomain()
    {
        return $this->domain;
    }
}
