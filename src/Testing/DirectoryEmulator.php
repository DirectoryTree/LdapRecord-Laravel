<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

class DirectoryEmulator extends DirectoryFake
{
    /**
     * Whether the emulator will use an in-memory SQLite database.
     *
     * @var bool
     */
    public static $usingInMemoryDatabase = true;

    /**
     * Use a cached SQLite file database for persistent data.
     *
     * @return void
     */
    public static function useCachedDirectory()
    {
        static::$usingInMemoryDatabase = false;
    }

    /**
     * Use an in-memory SQLite database.
     *
     * @return void
     */
    public static function useMemoryDirectory()
    {
        static::$usingInMemoryDatabase = true;
    }

    /**
     * Setup the fake connections.
     *
     * @param string|null $name
     *
     * @return EmulatedQueryConnection
     *
     * @throws \LdapRecord\ContainerException
     */
    public static function setup($name = null)
    {
        $fake = parent::setup($name);

        EloquentFactory::initialize(static::$usingInMemoryDatabase);

        return $fake;
    }

    /**
     * Make a fake connection.
     *
     * @param array $config
     *
     * @return EmulatedQueryConnection
     */
    public static function makeConnectionFake(array $config = [])
    {
        return new EmulatedQueryConnection($config, new LdapFake());
    }

    /**
     * Tear down the fake directory.
     *
     * @return void
     */
    public static function teardown()
    {
        EloquentFactory::teardown();
    }
}
