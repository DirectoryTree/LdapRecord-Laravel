<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Synchronizing;

class LogSynchronizing
{
    /**
     * Handle the event.
     *
     * @param Synchronizing $event
     *
     * @return void
     */
    public function handle(Synchronizing $event)
    {
        Log::info("User '{$event->user->getCommonName()}' is being synchronized.");
    }
}
