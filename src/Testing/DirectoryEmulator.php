<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\FakeDirectory as BaseDirectoryFake;
use LdapRecord\Testing\FakeLdapConnection;

class DirectoryEmulator extends BaseDirectoryFake
{
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

        EloquentFactory::migrate();

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
        return new EmulatedQueryConnection($config, new FakeLdapConnection());
    }

    /**
     * Tear down the fake directory.
     *
     * @return void
     */
    public static function teardown()
    {
        EloquentFactory::rollback();
    }
}
