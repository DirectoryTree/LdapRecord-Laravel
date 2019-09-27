<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;

class LogDiscovery
{
    /**
     * Handle the event.
     *
     * @param DiscoveredWithCredentials $event
     *
     * @return void
     */
    public function handle(DiscoveredWithCredentials $event)
    {
        Log::info("User '{$event->user->getCommonName()}' has been successfully found for authentication.");
    }
}
