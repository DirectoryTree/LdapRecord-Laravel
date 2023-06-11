<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model;

trait DetectsSoftDeletes
{
    /**
     * Determine if the model is using soft-deletes.
     */
    protected function isUsingSoftDeletes(Model $model): bool
    {
        return method_exists($model, 'trashed');
    }
}
