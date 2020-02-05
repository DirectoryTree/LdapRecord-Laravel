<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Connection;

class FakeLdapConnection extends Connection
{
    /**
     * Create a new Eloquent LDAP query builder.
     *
     * @return EloquentLdapBuilder|\LdapRecord\Query\Builder
     *
     * @throws \LdapRecord\Configuration\ConfigurationException
     */
    public function query()
    {
        return (new EloquentLdapBuilder($this))->in($this->configuration->get('base_dn'));
    }

    /**
     * {@inheritDoc}
     */
    public function isConnected()
    {
        return true;
    }
}
