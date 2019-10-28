<?php

namespace LdapRecord\Laravel\Scopes;

use LdapRecord\Query\Model\Builder;

class UidScope implements ScopeInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder)
    {
        $builder->whereHas('uid');
    }
}
