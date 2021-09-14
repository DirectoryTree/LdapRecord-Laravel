<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\DirectoryFake;

class DirectoryEmulator extends DirectoryFake
{
    /**
     * Setup the fake connections.
     *
     * @param string|null $name
     * @param array       $config
     *
     * @return EmulatedConnectionFake
     *
     * @throws \LdapRecord\ContainerException
     */
    public static function setup($name = null, array $config = [])
    {
        return tap(parent::setup($name), function (EmulatedConnectionFake $fake) use ($name, $config) {
            $fake->name($name);

            app(LdapDatabaseManager::class)->connection($name, $config);
        });
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
        return EmulatedConnectionFake::make($config)->shouldBeConnected();
    }

    /**
     * Tear down the fake directory.
     *
     * @return void
     */
    public static function tearDown()
    {
        parent::tearDown();

        app(LdapDatabaseManager::class)->teardown();
    }
}
