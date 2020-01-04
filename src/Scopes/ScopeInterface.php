<?php

namespace LdapRecord\Laravel\Scopes;

use LdapRecord\Query\Model\Builder;

interface ScopeInterface
{
    /**
     * Apply the scope to the LDAP query.
     *
     * @param Builder $builder
     *
     * @return void
     */
    public function apply(Builder $builder);
}
