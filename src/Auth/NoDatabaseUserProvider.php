<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Facades\Resolver;
use Illuminate\Contracts\Auth\UserProvider;
use LdapRecord\Laravel\Traits\ValidatesUsers;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\Events\AuthenticationSuccessful;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\AuthenticatedWithCredentials;

class NoDatabaseUserProvider implements UserProvider
{
    use ValidatesUsers;

    /**
     *  {@inheritdoc}
     */
    public function retrieveById($identifier)
    {
        $user = Resolver::byId($identifier);

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
        if ($user = Resolver::byCredentials($credentials)) {
            Event::dispatch(new DiscoveredWithCredentials($user));

            return $user;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        if (Resolver::authenticate($user, $credentials)) {
            Event::dispatch(new AuthenticatedWithCredentials($user));

            if ($this->getLdapUserValidator($user)) {
                Event::dispatch(new AuthenticationSuccessful($user));

                return true;
            }

            Event::dispatch(new AuthenticationRejected($user));
        }

        return false;
    }
}
