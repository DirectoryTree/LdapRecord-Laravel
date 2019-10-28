<?php

namespace LdapRecord\Laravel\Auth;

interface LdapAuthenticatable
{
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
     * Get the users LDAP GUID.
     *
     * @return string
     */
    public function getLdapGuid();

    /**
     * Get the users LDAP GUID database column name.
     *
     * @return string
     */
    public function getLdapGuidName();

    /**
     * Set the users LDAP GUID.
     *
     * @param string $guid
     *
     * @return void
     */
    public function setLdapGuid($guid);
}
