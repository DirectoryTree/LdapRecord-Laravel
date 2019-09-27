<?php

namespace LdapRecord\Laravel\Tests\Scopes;

use Adldap\Query\Builder;
use LdapRecord\Laravel\Scopes\ScopeInterface;

class JohnDoeScope implements ScopeInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder)
    {
        $builder->whereCn('John Doe');
    }
}
