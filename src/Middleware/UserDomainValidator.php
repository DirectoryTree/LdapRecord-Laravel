<?php

namespace LdapRecord\Laravel\Middleware;

use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Model;

class UserDomainValidator
{
    /**
     * Determine if the user passes domain validation.
     */
    public function __invoke(Model $user, string $username, ?string $domain = null): bool
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
     */
    protected function getDomainComponents(string $dn): array
    {
        return DistinguishedName::build($dn)->components('dc');
    }

    /**
     * Determine if the domain exists in the given components.
     */
    protected function domainExistsInComponents(string $domain, array $components): bool
    {
        return collect($components)->map(function ($component) {
            [,$value] = $component;

            return strtolower($value);
        })->contains(strtolower($domain));
    }
}
