<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Authenticating;

class LogAuthentication
{
    /**
     * Handle the event.
     *
     * @param Authenticating $event
     *
     * @return void
     */
    public function handle(Authenticating $event)
    {
        Log::info("User [{$event->user->getName()}] is authenticating.");
    }
}
