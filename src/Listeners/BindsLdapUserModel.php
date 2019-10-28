<?php

namespace LdapRecord\Laravel\Listeners;

use LdapRecord\Laravel\DomainRegistrar;
use LdapRecord\Laravel\Traits\HasLdapUser;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

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
        if ($event->user instanceof LdapAuthenticatable && $this->canBind($event->user)) {
            /** @var \LdapRecord\Laravel\Domain $domain */
            $domain = app(DomainRegistrar::class)->get($event->user->getLdapDomain());

            $event->user->setLdapUser(
                $domain->locate()->byModel($event->user)
            );
        }
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
