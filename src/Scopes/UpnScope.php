<?php

namespace LdapRecord\Laravel\Scopes;

use LdapRecord\Query\Model\Builder;

class UpnScope implements ScopeInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder)
    {
        $builder->whereHas('userprincipalname');
    }
}
