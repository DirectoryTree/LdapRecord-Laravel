<?php

namespace LdapRecord\Laravel\Testing;

trait EmulatedConnection
{
    public static function resolveConnection($connection = null)
    {
        return EloquentFactory::$connection;
    }
}