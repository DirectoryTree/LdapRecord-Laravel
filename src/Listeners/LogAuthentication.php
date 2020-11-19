<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Ldap\Binding;

class LogAuthentication
{
    /**
     * Handle the event.
     *
     * @param Binding $event
     *
     * @return void
     */
    public function handle(Binding $event)
    {
        Log::info("User [{$event->user->getName()}] is authenticating.");
    }
}
