<?php

namespace LdapRecord\Laravel;

interface LdapImportable
{
    /**
     * Get the database column name for the LDAP domain.
     */
    public function getLdapDomainColumn(): string;

    /**
     * Get the models LDAP domain.
     */
    public function getLdapDomain(): ?string;

    /**
     * Set the models LDAP domain.
     */
    public function setLdapDomain(?string $domain): void;

    /**
     * Get the database column name for the LDAP guid.
     */
    public function getLdapGuidColumn(): string;

    /**
     * Get the models LDAP GUID.
     */
    public function getLdapGuid(): ?string;

    /**
     * Set the models LDAP GUID.
     */
    public function setLdapGuid(?string $guid): void;
}
