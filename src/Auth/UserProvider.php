<?php

namespace LdapRecord\Laravel\Auth;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\UserProvider as LaravelUserProvider;
use Illuminate\Validation\ValidationException;
use LdapRecord\Laravel\LdapRecord;
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
     * The user resolver to use for finding the authenticating user.
     *
     * @var Closure
     */
    protected $userResolver;

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

        $this->userResolver = function ($credentials) {
            return $this->users->findByCredentials($credentials);
        };
    }

    /**
     * Set the callback to resolve users by.
     *
     * @param Closure $callback
     *
     * @return $this
     */
    public function resolveUsersUsing(Closure $callback)
    {
        $this->userResolver = $callback;

        return $this;
    }

    /**
     * Attempt to retrieve the user by their credentials.
     *
     * @param array $credentials
     *
     * @return mixed
     *
     * @throws ValidationException
     */
    protected function fetchLdapUserByCredentials(array $credentials)
    {
        try {
            return call_user_func($this->userResolver, $credentials);
        } catch (Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handle exceptions during user resolution.
     *
     * @param Exception $e
     *
     * @throws ValidationException
     */
    protected function handleException(Exception $e)
    {
        if ($e instanceof ValidationException) {
            throw $e;
        }

        if (! LdapRecord::failingQuietly()) {
            throw $e;
        }

        report($e);
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
