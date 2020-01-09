<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Contracts\Auth\UserProvider as LaravelUserProvider;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserRepository;

abstract class UserProvider implements LaravelUserProvider
{
    /**
     * The LDAP user repository instance.
     *
     * @var LdapUserRepository
     */
    protected $users;

    /**
     * The LDAP user authenticator instance.
     *
     * @var LdapUserAuthenticator
     */
    protected $auth;

    /**
     * Constructor.
     *
     * @param LdapUserAuthenticator $auth
     * @param LdapUserRepository    $users
     */
    public function __construct(LdapUserRepository $users, LdapUserAuthenticator $auth)
    {
        $this->users = $users;
        $this->auth = $auth;
    }

    /**
     * Set the LDAP user repository.
     *
     * @param LdapUserRepository $users
     */
    public function setLdapUserRepository(LdapUserRepository $users)
    {
        $this->users = $users;
    }

    /**
     * Get the LDAP user repository.
     *
     * @return LdapUserRepository
     */
    public function getLdapUserRepository()
    {
        return $this->users;
    }

    /**
     * Get the the LDAP user authenticator.
     *
     * @return LdapUserAuthenticator
     */
    public function getLdapUserAuthenticator()
    {
        return $this->auth;
    }

    /**
     * Set the LDAP user authenticator.
     *
     * @param LdapUserAuthenticator $auth
     */
    public function setLdapUserAuthenticator(LdapUserAuthenticator $auth)
    {
        $this->auth = $auth;
    }
}
