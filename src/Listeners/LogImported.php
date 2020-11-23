<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Import\Imported;

class LogImported
{
    /**
     * Handle the event.
     *
     * @param Imported $event
     *
     * @return void
     */
    public function handle(Imported $event)
    {
        Log::info("Object with name [{$event->ldap->getName()}] has been successfully imported.");
    }
}
