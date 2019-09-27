<?php

namespace LdapRecord\Laravel\Scopes;

use Adldap\Query\Builder;

class UidScope implements ScopeInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder)
    {
        $builder->whereHas($builder->getSchema()->userId());
    }
}
