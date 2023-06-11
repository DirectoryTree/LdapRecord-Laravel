<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Connection;

trait ResolvesEmulatedConnection
{
    /**
     * Resolve the emulator database connection.
     *
     * @param  string  $connection
     */
    public static function resolveConnection($connection = null): Connection
    {
        return app(LdapDatabaseManager::class)->connection($connection);
    }
}
