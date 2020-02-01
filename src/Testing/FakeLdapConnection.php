<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Connection;

class FakeLdapConnection extends Connection
{
    public function query()
    {
        return (new EloquentLdapBuilder($this))->in($this->configuration->get('base_dn'));
    }

    public function isConnected()
    {
        return true;
    }
}
