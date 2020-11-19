<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Auth\Rejected;

class LogAuthenticationRejection
{
    /**
     * Handle the event.
     *
     * @param Rejected $event
     */
    public function handle(Rejected $event)
    {
        Log::info("User [{$event->user->getName()}] has failed validation. They have been denied authentication.");
    }
}
