<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

class EmulatedDirectoryFake extends DirectoryFake
{
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
}
