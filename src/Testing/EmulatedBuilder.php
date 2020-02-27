<?php

namespace LdapRecord\Laravel\Testing;

use LdapRecord\Models\Model;
use LdapRecord\Query\Builder;

class EmulatedBuilder extends Builder
{
    use EmulatesQueries;

    /**
     * Create a new Eloquent model builder.
     *
     * @param Model $model
     *
     * @return EmulatedModelBuilder|\LdapRecord\Query\Model\Builder
     */
    public function model(Model $model)
    {
        return (new EmulatedModelBuilder($this->connection))
            ->setModel($model)
            ->in($this->dn);
    }
}
