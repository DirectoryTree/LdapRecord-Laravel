<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Importing;

class LogImport
{
    /**
     * Handle the event.
     *
     * @param Importing $event
     *
     * @return void
     */
    public function handle(Importing $event)
    {
        Log::info("User '{$event->user->getCommonName()}' is being imported.");
    }
}
