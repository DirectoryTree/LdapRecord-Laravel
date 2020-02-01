<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Container;
use LdapRecord\Connection;

class FakeDirectory
{
    public static function setup()
    {
        tap(Container::getInstance(), function (Container $container) {
            collect($container->all())->each(function (Connection $connection, $name) use ($container) {
                // Replace the connection with a fake.
                $container->add(new FakeLdapConnection(), $name);
            });
        });

        EloquentFactory::setup();

        return new static;
    }
}
