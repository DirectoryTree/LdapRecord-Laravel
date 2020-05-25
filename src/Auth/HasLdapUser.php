<?php

namespace LdapRecord\Laravel\Auth;

use LdapRecord\Models\Model;

/** @property Model|null $ldap */
trait HasLdapUser
{
    use CreatesUserProvider;

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
}
