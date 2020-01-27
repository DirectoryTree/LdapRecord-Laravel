<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Models\Model;

trait MultiDomainAuthentication
{
    /**
     * Get the guard to be used during authentication.
     *
     * @return \Illuminate\Contracts\Auth\StatefulGuard
     */
    protected function getLdapGuard()
    {
        return Auth::guard(
            Auth::check() ? $this->getLdapGuardFromUser() : $this->getLdapGuardFromRequest()
        );
    }

    /**
     * Get the LDAP domain from the request.
     *
     * @return string|null
     */
    protected function getLdapGuardFromRequest()
    {
        return request('domain', config('ldap.default'));
    }

    /**
     * Get the LDAP domain from the authenticated user.
     *
     * @return string|null
     */
    protected function getLdapGuardFromUser()
    {
        $user = Auth::user();

        if ($user instanceof Model) {
            return $user->getConnectionName() ?? config('ldap.default');
        }

        if ($user instanceof LdapAuthenticatable) {
            return $user->getLdapDomain();
        }
    }
}
