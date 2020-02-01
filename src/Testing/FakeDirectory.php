<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Connection;
use LdapRecord\Container;

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
