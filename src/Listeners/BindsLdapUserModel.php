<?php

namespace LdapRecord\Laravel\Listeners;

use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Traits\HasLdapUser;

class BindsLdapUserModel
{
    /**
     * Binds the LDAP user record to their model.
     *
     * @param \Illuminate\Auth\Events\Login|\Illuminate\Auth\Events\Authenticated $event
     *
     * @return void
     */
    public function handle($event)
    {
//        if ($event->user instanceof LdapAuthenticatable && $this->canBind($event->user)) {
//            $domain = DomainRegistrar::$domains[$event->user->getLdapDomain()];
//
//            tap(new $domain, function (Domain $domain) use ($event) {
//                $event->user->setLdapUser(
//                    $domain->locate()->byModel($event->user)
//                );
//            });
//        }
    }

    /**
     * Determines if we're able to bind to the user.
     *
     * @param LdapAuthenticatable $user
     *
     * @return bool
     */
    protected function canBind(LdapAuthenticatable $user): bool
    {
        return array_key_exists(HasLdapUser::class, class_uses_recursive($user)) && is_null($user->ldap);
    }
}
