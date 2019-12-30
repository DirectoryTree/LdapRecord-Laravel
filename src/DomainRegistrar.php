<?php

namespace LdapRecord\Laravel;

use Illuminate\Support\Facades\Log;
use LdapRecord\Container;
use LdapRecord\LdapRecordException;

class DomainRegistrar
{
    /**
     * The LDAP domains of the application.
     *
     * @var string[]
     */
    public static $domains = [];

    /**
     * Whether logging is enabled for LDAP operations.
     *
     * @var bool
     */
    public static $logging = false;

    /**
     * Set the LDAP domains to register.
     *
     * @param array|string $domains
     *
     * @return void
     */
    public static function register($domains = [])
    {
        static::$domains = (array) $domains;
    }

    /**
     * Enable or disable LDAP operation logging.
     *
     * @param bool $enabled
     */
    public static function logging($enabled = false)
    {
        static::$logging = $enabled;
    }

    /**
     * Setup the LDAP domains.
     *
     * @return void
     */
    public static function setup()
    {
        /** @var Domain $domain */
        foreach (static::$domains as $domain) {
            $connection = $domain::getNewConnection();

            if ($domain::$shouldAutoConnect && ! $connection->isConnected()) {
                try {
                    $connection->connect();
                } catch (LdapRecordException $ex) {
                    if (static::$logging) {
                        Log::error($ex->getMessage());
                    }
                }
            }

            Container::addConnection($connection, $domain);
        }
    }
}
