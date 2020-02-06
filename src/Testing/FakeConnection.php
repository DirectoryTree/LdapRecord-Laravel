<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Connection;
use LdapRecord\Models\Model;

class FakeConnection extends Connection
{
    /**
     * The currently bound LDAP user.
     *
     * @var Model
     */
    protected $user;

    /**
     * The underlying fake LDAP connection.
     *
     * @var FakeLdapConnection
     */
    protected $ldap;

    /**
     * Constructor.
     *
     * @param array              $config
     * @param FakeLdapConnection $ldap
     */
    public function __construct($config, FakeLdapConnection $ldap)
    {
        parent::__construct($config, $ldap);
    }

    /**
     * Set the user to authenticate as.
     *
     * @param Model $user
     */
    public function actingAs(Model $user)
    {
        $this->user = $user;

        $this->ldap->setBound(true);

        return $this;
    }

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
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return true;
    }
}
