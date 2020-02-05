<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Container;

class FakeDirectory
{
    /**
     * Setup the fake connections.
     *
     * @param string|array $connections
     *
     * @throws \LdapRecord\ContainerException
     *
     * @return void
     */
    public static function setup($connections = [])
    {
        foreach ((array) $connections as $connection) {
            $config = Container::getConnection($connection)->getConfiguration();

            // Replace the connection with a fake.
            Container::addConnection(
                new FakeLdapConnection($config->all()),
                $connection
            );
        }

        EloquentFactory::migrate();
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
