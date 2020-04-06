<?php

namespace LdapRecord\Laravel;

interface LdapImportable
{
    /**
     * Get the database column name for the LDAP domain.
     *
     * @return string
     */
    public function getLdapDomainColumn();

    /**
     * Get the models LDAP domain.
     *
     * @return string
     */
    public function getLdapDomain();

    /**
     * Set the models LDAP domain.
     *
     * @param string $domain
     *
     * @return void
     */
    public function setLdapDomain($domain);

    /**
     * Get the database column name for the LDAP guid.
     *
     * @return string
     */
    public function getLdapGuidColumn();

    /**
     * Get the models LDAP GUID.
     *
     * @return string
     */
    public function getLdapGuid();

    /**
     * Set the models LDAP GUID.
     *
     * @param string $guid
     *
     * @return void
     */
    public function setLdapGuid($guid);
}
