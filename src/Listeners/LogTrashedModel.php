<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\Auth\EloquentModelTrashed;

class LogTrashedModel
{
    /**
     * Handle the event.
     *
     * @param EloquentModelTrashed $event
     *
     * @return void
     */
    public function handle(EloquentModelTrashed $event)
    {
        Log::info("User [{$event->user->getName()}] was denied authentication because their model is soft-deleted.");
    }
}
