<?php

namespace LdapRecord\Laravel\Auth;

use LdapRecord\Laravel\Events\Auth\Completed;
use Illuminate\Contracts\Auth\Authenticatable;

class NoDatabaseUserProvider extends UserProvider
{
    /**
     *  {@inheritdoc}
     */
    public function retrieveById($identifier)
    {
        return $this->users->findByGuid($identifier);
    }

    /**
     *  {@inheritdoc}
     */
    public function retrieveByToken($identifier, $token)
    {
        // We can't retrieve LDAP users via remember
        // token, as we have nowhere to store them.
    }

    /**
     *  {@inheritdoc}
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        // LDAP users cannot contain remember tokens.
    }

    /**
     *  {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials)
    {
        return $this->users->findByCredentials($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (! $this->auth->attempt($user, $credentials['password'])) {
            return;
        }

        event(new Completed($user));

        return true;
    }
}
