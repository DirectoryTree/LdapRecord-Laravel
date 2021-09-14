<?php

namespace LdapRecord\Laravel;

use LdapRecord\Laravel\Import\UserSynchronizer;

class LdapRecord
{
    /**
     * Whether LdapRecord will catch exceptions during authentication.
     *
     * @var bool
     */
    public static $failingQuietly = true;

    /**
     * Don't catch exceptions during authentication.
     *
     * @return void
     */
    public static function failLoudly()
    {
        static::$failingQuietly = false;
    }

    /**
     * Whether LdapRecord is catching exceptions during authentication.
     *
     * @return bool
     */
    public static function failingQuietly()
    {
        return static::$failingQuietly;
    }

    /**
     * Register a class that should be used for authenticating LDAP users.
     *
     * @param string|\Closure $class
     *
     * @return void
     */
    public static function authenticateUsersUsing($class)
    {
        app()->bind(LdapUserAuthenticator::class, $class);
    }

    /**
     * Register a class that should be used for locating LDAP users.
     *
     * @param string|\Closure $class
     *
     * @return void
     */
    public static function locateUsersUsing($class)
    {
        app()->bind(LdapUserRepository::class, $class);
    }

    /**
     * Register a class that should be used for synchronizing LDAP users.
     *
     * @param string|\Closure $class
     *
     * @return void
     */
    public static function synchronizeUsersUsing($class)
    {
        app()->bind(UserSynchronizer::class, $class);
    }
}
