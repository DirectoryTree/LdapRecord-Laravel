<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\FakeDirectory as BaseDirectoryFake;

class FakeSearchableDirectory extends BaseDirectoryFake
{
    /**
     * Setup the fake connections.
     *
     * @param string|null $name
     *
     * @return FakeSearchableConnection
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
     * @return FakeSearchableConnection
     */
    public static function makeConnectionFake(array $config = [])
    {
        return new FakeSearchableConnection($config);
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
