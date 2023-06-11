<?php

namespace LdapRecord\Laravel;

trait ImportableFromLdap
{
    /**
     * Get the database column name of the domain.
     */
    public function getLdapDomainColumn(): string
    {
        return 'domain';
    }

    /**
     * Get the models LDAP domain.
     */
    public function getLdapDomain(): ?string
    {
        return $this->{$this->getLdapDomainColumn()};
    }

    /**
     * Set the models LDAP domain.
     */
    public function setLdapDomain(?string $domain): void
    {
        $this->{$this->getLdapDomainColumn()} = $domain;
    }

    /**
     * Get the models LDAP GUID database column name.
     */
    public function getLdapGuidColumn(): string
    {
        return 'guid';
    }

    /**
     * Get the models LDAP GUID.
     */
    public function getLdapGuid(): ?string
    {
        return $this->{$this->getLdapGuidColumn()};
    }

    /**
     * Set the models LDAP GUID.
     */
    public function setLdapGuid(?string $guid): void
    {
        $this->{$this->getLdapGuidColumn()} = $guid;
    }
}
