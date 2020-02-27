<?php

namespace LdapRecord\Laravel\Testing;

trait ResolvesEmulatedConnection
{
    public static function resolveConnection($connection = null)
    {
        return app(LdapDatabaseManager::class)->connection($connection);
    }
}
