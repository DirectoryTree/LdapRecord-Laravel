<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Testing\ConnectionFake;

class EmulatedConnectionFake extends ConnectionFake
{
    /**
     * The emulated connection name.
     *
     * @var string
     */
    protected $name;

    /**
     * Get or set the name of the connection fake.
     *
     * @param string|null $name
     *
     * @return $this|string
     */
    public function name($name = null)
    {
        if (is_null($name)) {
            return $this->name;
        }

        $this->name = $name;

        return $this;
    }

    /**
     * Create a new Eloquent LDAP query builder.
     *
     * @return EmulatedBuilder|\LdapRecord\Query\Builder
     *
     * @throws \LdapRecord\Configuration\ConfigurationException
     */
    public function query()
    {
        return (new EmulatedBuilder($this))
            ->setBaseDn($this->configuration->get('base_dn'));
    }
}
