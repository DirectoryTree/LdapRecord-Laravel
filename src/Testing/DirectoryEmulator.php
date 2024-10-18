<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\DirectoryFake;

class DirectoryEmulator extends DirectoryFake
{
    /**
     * Setup the fake connections.
     *
     * @return EmulatedConnectionFake
     *
     * @throws \LdapRecord\ContainerException
     */
    public static function setup(?string $name = null, array $config = []): ConnectionFake
    {
        return tap(parent::setup($name), function (EmulatedConnectionFake $fake) use ($name, $config) {
            $fake->name($name);

            app(LdapDatabaseManager::class)->connection($name, $config);
        });
    }

    /**
     * Make a fake connection.
     */
    public static function makeConnectionFake(array $config = []): EmulatedConnectionFake
    {
        return EmulatedConnectionFake::make($config)->shouldBeConnected();
    }

    /**
     * Tear down the fake directory.
     */
    public static function tearDown(): void
    {
        app(LdapDatabaseManager::class)->teardown();

        parent::tearDown();
    }
}
