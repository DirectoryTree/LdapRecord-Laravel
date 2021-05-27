<?php

namespace LdapRecord\Laravel\Testing\Emulated;

use Illuminate\Support\Arr;
use LdapRecord\Laravel\Testing\EmulatesQueries;

trait EmulatesModelQueries
{
    use EmulatesQueries {
        addFilterToDatabaseQuery as baseAddFilterToDatabaseQuery;
    }

    /**
     * @inheritdoc
     */
    public function newInstance($baseDn = null)
    {
        return (new self($this->connection))
            ->setModel($this->model)
            ->setBaseDn($baseDn);
    }

    /**
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
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
     * @inheritdoc
     */
    public function deleteAttributes($dn, array $attributes)
    {
        if (! $model = $this->find($dn)) {
            return false;
        }

        foreach ($attributes as $attribute => $value) {
            if (empty($value)) {
                $model->{$attribute} = null;
            } elseif (Arr::exists($model->{$attribute} ?? [], $attribute)) {
                $model->{$attribute} = array_values(
                    array_diff($model->{$attribute}, (array) $value)
                );
            }
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
    public function parse($resource)
    {
        return $resource->toArray();
    }

    /**
     * @inheritdoc
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

    /**
     * @inheritdoc
     */
    protected function addFilterToDatabaseQuery($query, $field, $operator, $value)
    {
        if ($field === 'anr') {
            return $query->whereIn('name', $this->model->getAnrAttributes())
                ->whereHas('values', function ($query) use ($value) {
                    $query->where('value', 'like', "%$value%");
                });
        }

        return $this->baseAddFilterToDatabaseQuery($query, $field, $operator, $value);
    }

    /**
     * Override the possibility of the underlying LDAP model being compatible with ANR.
     *
     * @return false
     */
    protected function modelIsCompatibleWithAnr()
    {
        return true;
    }
}
