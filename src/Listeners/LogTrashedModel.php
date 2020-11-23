<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Auth\EloquentUserTrashed;

class LogTrashedModel
{
    /**
     * Handle the event.
     *
     * @param EloquentUserTrashed $event
     *
     * @return void
     */
    public function handle(EloquentUserTrashed $event)
    {
        Log::info("User [{$event->ldap->getName()}] was denied authentication because their model is soft-deleted.");
    }
}
