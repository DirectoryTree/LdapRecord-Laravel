<?php

namespace LdapRecord\Laravel\Scopes;

use LdapRecord\Query\Model\Builder;

class MemberOfScope implements ScopeInterface
{
    /**
     * {@inheritdoc}
     */
    public function apply(Builder $builder)
    {
        $builder->select($this->getSelectedAttributes($builder));
    }

    /**
     * Retrieve the attributes to select for the scope.
     *
     * This merges the current queries selected attributes so we
     * don't overwrite any other scopes selected attributes.
     *
     * @param Builder $builder
     *
     * @return array
     */
    protected function getSelectedAttributes(Builder $builder)
    {
        $selected = $builder->getSelects();

        return array_merge($selected, ['memberof']);
    }
}
