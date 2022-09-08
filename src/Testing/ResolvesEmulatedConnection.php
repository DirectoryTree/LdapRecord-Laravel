<?php

namespace LdapRecord\Laravel\Testing;

trait ResolvesEmulatedConnection
{
    /**
     * Resolve the emulator database connection.
     *
     * @param string $connection
     *
     * @return \Illuminate\Database\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return app(LdapDatabaseManager::class)->connection($connection);
    }
}
