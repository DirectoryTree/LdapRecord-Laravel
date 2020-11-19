<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Ldap\BindFailed;

class LogAuthenticationFailure
{
    /**
     * Handle the event.
     *
     * @param BindFailed $event
     *
     * @return void
     */
    public function handle(BindFailed $event)
    {
        Log::info("User [{$event->user->getName()}] has failed LDAP authentication.");
    }
}
