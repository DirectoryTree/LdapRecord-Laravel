<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\AuthenticatedModelTrashed;

class LogTrashedModel
{
    /**
     * Handle the event.
     *
     * @param AuthenticatedModelTrashed $event
     *
     * @return void
     */
    public function handle(AuthenticatedModelTrashed $event)
    {
        Log::info("User [{$event->user->getName()}] was denied authentication because their model is soft-deleted.");
    }
}
