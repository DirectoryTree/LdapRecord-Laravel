<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Import\Synchronized;

class LogSynchronized
{
    /**
     * Handle the event.
     *
     * @param Synchronized $event
     *
     * @return void
     */
    public function handle(Synchronized $event)
    {
        Log::info("Object with name [{$event->object->getName()}] has been successfully synchronized.");
    }
}
