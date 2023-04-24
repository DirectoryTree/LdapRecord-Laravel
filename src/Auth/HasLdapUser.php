<?php

namespace LdapRecord\Laravel\Auth;

use LdapRecord\Models\Model;

/** @property Model|null $ldap */
trait HasLdapUser
{
    use CreatesUserProvider;

    /**
     * The currently authenticated users LDAP model.
     */
    protected ?Model $ldapUserModel = null;

    /**
     * Get the currently authenticated LDAP users model.
     */
    public function getLdapAttribute(): ?Model
    {
        if (! $this instanceof LdapAuthenticatable) {
            return null;
        }

        if (! $guard = $this->getCurrentAuthGuard()) {
            return null;
        }

        if (! $provider = $this->getCurrentAuthProvider($guard)) {
            return null;
        }

        if (! $provider instanceof UserProvider) {
            return null;
        }

        if (! isset($this->ldapUserModel)) {
            $this->ldapUserModel = $provider->getLdapUserRepository()->findByModel($this);
        }

        return $this->ldapUserModel;
    }
}
