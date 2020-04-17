<?php

namespace LdapRecord\Laravel\Listeners;

use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\Events\DeletedMissing;

class LogDeletedMissing
{
    /**
     * Handle the event.
     *
     * @param DeletedMissing $event
     *
     * @return void
     */
    public function handle(DeletedMissing $event)
    {
        $key = $event->eloquent->getKeyName();

        $event->ids->each(function ($id) use ($key) {
            Log::info("User with [$key = $id] has been soft-deleted due to being missing from LDAP import.");
        });
    }
}
