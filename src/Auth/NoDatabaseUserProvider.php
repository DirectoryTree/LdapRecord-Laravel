<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\AuthenticatedWithCredentials;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;

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
        if ($user = $this->users->findByCredentials($credentials)) {
            event(new DiscoveredWithCredentials($user));

            return $user;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if ($this->auth->attempt($user, $credentials['password'])) {
            event(new AuthenticatedWithCredentials($user));

            return true;
        }

        return false;
    }
}
