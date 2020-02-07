<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\FakeConnection as BaseFakeConnection;

class EmulatedQueryConnection extends BaseFakeConnection
{
    /**
     * Create a new Eloquent LDAP query builder.
     *
     * @return EmulatedBuilder|\LdapRecord\Query\Builder
     *
     * @throws \LdapRecord\Configuration\ConfigurationException
     */
    public function query()
    {
        return (new EmulatedBuilder($this))->in($this->configuration->get('base_dn'));
    }
}
