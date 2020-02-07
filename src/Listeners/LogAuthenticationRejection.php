<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\AuthenticationRejected;

class LogAuthenticationRejection
{
    /**
     * Handle the event.
     *
     * @param AuthenticationRejected $event
     */
    public function handle(AuthenticationRejected $event)
    {
        Log::info("User [{$event->user->getName()}] has failed validation. They have been denied authentication.");
    }
}
