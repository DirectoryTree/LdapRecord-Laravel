<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Models\Model;

trait HasLdapUser
{
    /**
     * The currently authenticated users LDAP model.
     *
     * @var Model|null
     */
    protected $ldapUserModel;

    /**
     * Get the currently authenticated LDAP users model.
     *
     * @return Model|null
     */
    public function getLdapAttribute()
    {
        if (! $this instanceof LdapAuthenticatable) {
            return;
        }

        if (! $guard = $this->getCurrentAuthGuard()) {
            return;
        }

        if (! $provider = $this->getCurrentAuthProvider($guard)) {
            return;
        }

        if (! $provider instanceof UserProvider) {
            return;
        }

        if (! isset($this->ldapUserModel)) {
            $this->ldapUserModel = $provider->getLdapUserRepository()->findByModel($this);
        }

        return $this->ldapUserModel;
    }

    /**
     * Get the currently authenticated users guard name.
     *
     * @return string|null
     */
    protected function getCurrentAuthGuard()
    {
        foreach (config('auth.guards') as $guard => $config) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }
    }

    /**
     * Get the authentication user provider.
     *
     * @param string $guard
     *
     * @return \Illuminate\Contracts\Auth\UserProvider|null
     */
    protected function getCurrentAuthProvider($guard)
    {
        return Auth::createUserProvider(
            config("auth.guards.$guard.provider")
        );
    }
}
