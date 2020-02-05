<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Models\Model;
use LdapRecord\Query\Builder;

class EloquentLdapBuilder extends Builder
{
    /**
     * {@inheritDoc}
     */
    public function getSelects()
    {
        return $this->columns ?? [];
    }

    /**
     * Create a new Eloquent model builder.
     *
     * @param Model $model
     *
     * @return EloquentModelLdapBuilder|\LdapRecord\Query\Model\Builder
     */
    public function model(Model $model)
    {
        return (new EloquentModelLdapBuilder($this->connection))
            ->setModel($model)
            ->in($this->dn);
    }
}
