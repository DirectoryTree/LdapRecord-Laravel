<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

class DirectoryEmulator extends DirectoryFake
{
    /**
     * Setup the fake connections.
     *
     * @param string|null $name
     *
     * @return EmulatedConnectionFake
     *
     * @throws \LdapRecord\ContainerException
     */
    public static function setup($name = null)
    {
        return tap(parent::setup($name))->name($name);
    }

    /**
     * Make a fake connection.
     *
     * @param array $config
     *
     * @return EmulatedConnectionFake
     */
    public static function makeConnectionFake(array $config = [])
    {
        return new EmulatedConnectionFake($config, new LdapFake());
    }

    /**
     * Tear down the fake directory.
     *
     * @return void
     */
    public static function teardown()
    {
        app(LdapDatabaseManager::class)->teardown();
    }
}
