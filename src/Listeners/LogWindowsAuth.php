<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;

class LogWindowsAuth
{
    /**
     * Handle the event.
     *
     * @param AuthenticatedWithWindows $event
     *
     * @return void
     */
    public function handle(AuthenticatedWithWindows $event)
    {
        Log::info("User [{$event->user->getName()}] has successfully authenticated via NTLM.");
    }
}
