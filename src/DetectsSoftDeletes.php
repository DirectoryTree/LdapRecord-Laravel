<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model;

trait DetectsSoftDeletes
{
    /**
     * Determine if the model is using soft-deletes.
     *
     * @param Model $model
     *
     * @return bool
     */
    protected function isUsingSoftDeletes(Model $model)
    {
        return method_exists($model, 'trashed');
    }
}
