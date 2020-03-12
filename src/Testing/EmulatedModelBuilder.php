<?php

namespace LdapRecord\Laravel\Testing;

use Exception;
use Illuminate\Support\Arr;
use LdapRecord\Query\Model\Builder;
use Ramsey\Uuid\Uuid;

class EmulatedModelBuilder extends Builder
{
    use EmulatesQueries;

    /**
     * {@inheritdoc}
     */
    public function newInstance($baseDn = null)
    {
        return (new self($this->connection))
            ->in($baseDn)
            ->setModel($this->model);
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail($dn, $columns = ['*'])
    {
        if ($database = $this->findEloquentModelByDn($dn)) {
            return $this->transformDatabaseAttributesToLdapModel($database->toArray());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findByGuidOrFail($guid, $columns = [])
    {
        if ($database = $this->findEloquentModelByGuid($guid)) {
            return $this->transformDatabaseAttributesToLdapModel($database->toArray());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function insert($dn, array $attributes)
    {
        if (Arr::get($attributes, 'objectclass') == null) {
            throw new Exception('LDAP objects must have object classes to be created.');
        }

        $model = tap($this->newEloquentModel(), function ($model) use ($dn, $attributes) {
            $guid = Arr::pull($attributes, $this->model->getGuidKey());

            $model->dn = $dn;
            $model->parent_dn = $this->model->getParentDn($dn);
            $model->guid = $guid[0] ?? Uuid::uuid4()->toString();
            $model->name = $this->model->getCreatableRdn();
            $model->domain = $this->model->getConnectionName();
            $model->save();
        });

        foreach ($attributes as $name => $values) {
            $attribute = $model->attributes()->create(['name' => $name]);

            foreach ($values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function insertAttributes($dn, array $attributes)
    {
        if ($model = $this->find($dn)) {
            foreach ($attributes as $name => $values) {
                $model->{$name} = array_merge($model->{$name} ?? [], Arr::wrap($values));
            }

            return $model->save();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function update($dn, array $modifications)
    {
        /** @var LdapObject $model */
        $model = $this->findEloquentModelByDn($dn);

        if (! $model) {
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
        if ($model = $this->find($dn)) {
            foreach ($attributes as $name => $values) {
                $model->{$name} = $values;
            }

            return $model->save();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteAttributes($dn, array $attributes)
    {
        if ($model = $this->find($dn)) {
            foreach ($attributes as $attribute) {
                $model->{$attribute} = null;
            }

            return $model->save();
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    protected function process(array $results)
    {
        return $this->model->newCollection($results)->transform(function ($attributes) {
            return $this->transformDatabaseAttributesToLdapModel($attributes);
        });
    }

    /**
     * Transforms an array of database attributes into an LdapRecord model.
     *
     * @param array $attributes
     *
     * @return \LdapRecord\Models\Model
     */
    protected function transformDatabaseAttributesToLdapModel(array $attributes)
    {
        unset($attributes['parent_dn']);

        $dn = Arr::pull($attributes, 'dn');

        $transformedAttributes = collect(Arr::pull($attributes, 'attributes'))->mapWithKeys(function ($attribute) {
            return [$attribute['name'] => collect($attribute['values'])->map->value->toArray()];
        })->when(! empty($this->only), function ($attributes) {
            return $attributes->filter(function ($value, $key) {
                return in_array($key, $this->only);
            });
        })->toArray();

        $this->only = [];

        return $this->model->newInstance()
            ->setDn($dn)
            ->setRawAttributes($transformedAttributes);
    }
}
