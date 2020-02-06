<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\FakeConnection as BaseFakeConnection;

class FakeSearchableConnection extends BaseFakeConnection
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
}
