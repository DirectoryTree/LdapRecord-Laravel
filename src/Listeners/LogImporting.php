<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Importing;

class LogImporting
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
        Log::info("Object with name [{$event->user->getName()}] is being imported.");
    }
}
