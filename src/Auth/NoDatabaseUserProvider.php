<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\AuthenticatedWithCredentials;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\Events\AuthenticationSuccessful;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;

class NoDatabaseUserProvider extends UserProvider
{
    /**
     *  {@inheritdoc}
     */
    public function retrieveById($identifier)
    {
        $user = $this->domain->locate()->byGuid($identifier);

        // We'll verify we have the correct instance just to ensure we
        // don't return an incompatible model that may be returned.
        return $user instanceof Authenticatable ? $user : null;
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
        if ($user = $this->domain->locate()->byCredentials($credentials)) {
            event(new DiscoveredWithCredentials($user));

            return $user;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if ($this->domain->auth()->attempt($user, $credentials)) {
            event(new AuthenticatedWithCredentials($user));

            if ($this->domain->validator($user)->passes()) {
                event(new AuthenticationSuccessful($user));

                return true;
            }

            event(new AuthenticationRejected($user));
        }

        return false;
    }
}
