<?php

namespace LdapRecord\Laravel;

trait ImportableFromLdap
{
    /**
     * Get the database column name of the domain.
     *
     * @return string
     */
    public function getLdapDomainColumn()
    {
        return 'domain';
    }

    /**
     * Get the models LDAP domain.
     *
     * @return string
     */
    public function getLdapDomain()
    {
        return $this->{$this->getLdapDomainColumn()};
    }

    /**
     * Set the models LDAP domain.
     *
     * @param string $domain
     *
     * @return void
     */
    public function setLdapDomain($domain)
    {
        $this->{$this->getLdapDomainColumn()} = $domain;
    }

    /**
     * Get the models LDAP GUID database column name.
     *
     * @return string
     */
    public function getLdapGuidColumn()
    {
        return 'guid';
    }

    /**
     * Get the models LDAP GUID.
     *
     * @return string
     */
    public function getLdapGuid()
    {
        return $this->{$this->getLdapGuidColumn()};
    }

    /**
     * Set the models LDAP GUID.
     *
     * @param string $guid
     *
     * @return void
     */
    public function setLdapGuid($guid)
    {
        $this->{$this->getLdapGuidColumn()} = $guid;
    }
}
