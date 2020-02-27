<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\LdapFake;
use LdapRecord\Testing\DirectoryFake;

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
