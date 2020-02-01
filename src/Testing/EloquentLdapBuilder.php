<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Models\Model;
use LdapRecord\Query\Builder;

class EloquentLdapBuilder extends Builder
{
    public function getSelects()
    {
        return $this->columns ?? [];
    }

    public function model(Model $model)
    {
        return (new EloquentModelLdapBuilder($this->connection))
            ->setModel($model)
            ->in($this->dn);
    }
}
