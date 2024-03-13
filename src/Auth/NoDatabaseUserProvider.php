<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\Auth\Completed;
use LdapRecord\Models\Model;

class NoDatabaseUserProvider extends UserProvider
{
    /**
     * {@inheritdoc}
     */
    public function retrieveById($identifier): ?Model
    {
        return $this->users->findByGuid($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken($identifier, $token): void
    {
        // We can't retrieve LDAP users via remember
        // token, as we have nowhere to store them.
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        // LDAP users cannot contain remember tokens.
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials): ?Model
    {
        return $this->fetchLdapUserByCredentials($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (! $this->auth->attempt($user, $credentials['password'])) {
            return false;
        }

        event(new Completed($user));

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false)
    {
        // We can't rehash LDAP users passwords.
    }
}
