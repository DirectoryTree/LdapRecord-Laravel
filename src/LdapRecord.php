<?php

namespace LdapRecord\Laravel;

use Closure;
use LdapRecord\Laravel\Import\UserSynchronizer;

class LdapRecord
{
    /**
     * Whether LdapRecord will catch exceptions during authentication.
     */
    public static bool $failingQuietly = true;

    /**
     * Don't catch exceptions during authentication.
     */
    public static function failLoudly(): void
    {
        static::$failingQuietly = false;
    }

    /**
     * Whether LdapRecord is catching exceptions during authentication.
     */
    public static function failingQuietly(): bool
    {
        return static::$failingQuietly;
    }

    /**
     * Register a class that should be used for authenticating LDAP users.
     */
    public static function authenticateUsersUsing(Closure|string $class): void
    {
        app()->bind(LdapUserAuthenticator::class, $class);
    }

    /**
     * Register a class that should be used for locating LDAP users.
     */
    public static function locateUsersUsing(Closure|string $class): void
    {
        app()->bind(LdapUserRepository::class, $class);
    }

    /**
     * Register a class that should be used for synchronizing LDAP users.
     */
    public static function synchronizeUsersUsing(Closure|string $class): void
    {
        app()->bind(UserSynchronizer::class, $class);
    }
}
