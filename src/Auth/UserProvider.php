<?php

namespace LdapRecord\Laravel\Auth;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\UserProvider as LaravelUserProvider;
use Illuminate\Validation\ValidationException;
use LdapRecord\Laravel\LdapRecord;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Model;

abstract class UserProvider implements LaravelUserProvider
{
    /**
     * The LDAP user repository instance.
     */
    protected LdapUserRepository $users;

    /**
     * The LDAP user authenticator instance.
     */
    protected LdapUserAuthenticator $auth;

    /**
     * The user resolver to use for finding the authenticating user.
     */
    protected Closure $userResolver;

    /**
     * Constructor.
     */
    public function __construct(LdapUserRepository $users, LdapUserAuthenticator $auth)
    {
        $this->users = $users;
        $this->auth = $auth;

        $this->userResolver = function (array $credentials) {
            return $this->users->findByCredentials($credentials);
        };
    }

    /**
     * Set the callback to resolve users by.
     */
    public function resolveUsersUsing(Closure $callback): static
    {
        $this->userResolver = $callback;

        return $this;
    }

    /**
     * Attempt to retrieve the user by their credentials.
     *
     * @throws ValidationException
     */
    protected function fetchLdapUserByCredentials(array $credentials): ?Model
    {
        try {
            return call_user_func($this->userResolver, $credentials);
        } catch (Exception $e) {
            $this->handleException($e);

            return null;
        }
    }

    /**
     * Handle exceptions during user resolution.
     *
     * @throws ValidationException
     */
    protected function handleException(Exception $e): void
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
     */
    public function setLdapUserRepository(LdapUserRepository $users): void
    {
        $this->users = $users;
    }

    /**
     * Get the LDAP user repository.
     */
    public function getLdapUserRepository(): LdapUserRepository
    {
        return $this->users;
    }

    /**
     * Get the LDAP user authenticator.
     */
    public function getLdapUserAuthenticator(): LdapUserAuthenticator
    {
        return $this->auth;
    }

    /**
     * Set the LDAP user authenticator.
     */
    public function setLdapUserAuthenticator(LdapUserAuthenticator $auth): void
    {
        $this->auth = $auth;
    }
}
