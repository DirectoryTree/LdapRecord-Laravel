<?php

namespace LdapRecord\Laravel\Auth;

interface LdapAuthenticatable
{
    /**
     * Get the database column name for the LDAP domain.
     *
     * @return string
     */
    public function getLdapDomainColumn();

    /**
     * Get the users LDAP domain.
     *
     * @return string
     */
    public function getLdapDomain();

    /**
     * Set the users LDAP domain.
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
     * Get the users LDAP GUID.
     *
     * @return string
     */
    public function getLdapGuid();

    /**
     * Set the users LDAP GUID.
     *
     * @param string $guid
     *
     * @return void
     */
    public function setLdapGuid($guid);
}
