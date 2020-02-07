<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\ConnectionFake;

class EmulatedQueryConnection extends ConnectionFake
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
