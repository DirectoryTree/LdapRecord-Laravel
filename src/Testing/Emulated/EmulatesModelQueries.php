<?php

namespace LdapRecord\Laravel\Testing\Emulated;

use Illuminate\Support\Arr;
use LdapRecord\Laravel\Testing\EmulatesQueries;

trait EmulatesModelQueries
{
    use EmulatesQueries;

    /**
     * {@inheritdoc}
     */
    public function newInstance($baseDn = null)
    {
        return (new self($this->connection))->in($baseDn)->setModel($this->model);
    }

    /**
     * {@inheritdoc}
     */
    public function insertAttributes($dn, array $attributes)
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $name => $values) {
            $model->{$name} = array_merge($model->{$name} ?? [], Arr::wrap($values));
        }

        return $model->save();
    }

    /**
     * {@inheritdoc}
     */
    public function update($dn, array $modifications)
    {
        if (! $model = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        foreach ($modifications as $modification) {
            $this->applyBatchModificationToModel($model, $modification);
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function updateAttributes($dn, array $attributes)
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $name => $values) {
            $model->{$name} = $values;
        }

        return $model->save();
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAttributes($dn, array $attributes)
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $attribute) {
            $model->{$attribute} = null;
        }

        return $model->save();
    }

    /**
     * Parse the database query results.
     *
     * @param \Illuminate\Database\Eloquent\Collection $resource
     *
     * @return array
     */
    protected function parse($resource)
    {
        return $resource->toArray();
    }

    /**
     * {@inheritdoc}
     */
    protected function process(array $results)
    {
        return $this->model->newCollection($results)->transform(function ($object) {
            return $this->model->newInstance()
                ->setDn($object['dn'])
                ->setRawAttributes(array_merge(
                    $this->transform($object),
                    [$object['guid_key'] => [$object['guid']]]
                ));
        });
    }
}
