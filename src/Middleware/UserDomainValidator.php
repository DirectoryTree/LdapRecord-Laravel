<?php

namespace LdapRecord\Laravel\Middleware;

use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Model;

class UserDomainValidator
{
    /**
     * Determine if the user passes domain validation.
     *
     * @param Model       $user
     * @param string      $username
     * @param string|null $domain
     *
     * @return bool
     */
    public function __invoke(Model $user, $username, $domain = null)
    {
        if (empty($domain)) {
            return false;
        }

        if (! $components = $this->getDomainComponents($user->getDn())) {
            return false;
        }

        return $this->domainExistsInComponents($domain, $components);
    }

    /**
     * Get the domain components from the Distinguished Name.
     *
     * @param string $dn
     *
     * @return array
     */
    protected function getDomainComponents($dn)
    {
        return DistinguishedName::build($dn)->components('dc');
    }

    /**
     * Determine if the domain matches the first domain component.
     *
     * @param string $domain
     * @param array  $components
     *
     * @return bool
     */
    protected function domainExistsInComponents($domain, array $components)
    {
        [, $value] = reset($components);

        return strcasecmp($domain, $value) === 0;
    }
}
