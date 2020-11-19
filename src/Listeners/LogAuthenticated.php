<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Ldap\Bound;

class LogAuthenticated
{
    /**
     * Handle the event.
     *
     * @param Bound $event
     *
     * @return void
     */
    public function handle(Bound $event)
    {
        Log::info("User [{$event->user->getName()}] has successfully passed LDAP authentication.");
    }
}
