<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Support\Arr;
use LdapRecord\Models\BatchModification;
use LdapRecord\Query\Model\Builder;
use Ramsey\Uuid\Uuid;

class EloquentModelLdapBuilder extends Builder
{
    /**
     * Create a new instance of the configured model.
     *
     * @return LdapObject
     */
    public function newEloquentModel()
    {
        return EloquentFactory::createModel();
    }

    /**
     * Create a new Eloquent query from the configured model.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newEloquentQuery()
    {
        return $this->newEloquentModel()->query();
    }

    /**
     * Find the Eloquent model by distinguished name.
     *
     * @param string $dn
     *
     * @return LdapObject|null
     */
    public function findEloquentModelByDn($dn)
    {
        return $this->newEloquentQuery()->where('dn', '=', $dn)->first();
    }

    public function findOrFail($dn, $columns = ['*'])
    {
        if ($database = $this->findEloquentModelByDn($dn)) {
            $attributes = $database->attributes->mapWithKeys(function ($attribute) {
                return [$attribute->name => $attribute->values];
            });

            $model = new $this->model;
            $model->setDn($dn);
            $model->setRawAttributes($attributes->toArray());

            return $model;
        }
    }

    public function insert($dn, array $attributes)
    {
        $objectClasses = Arr::get($attributes, 'objectclass');

        if (is_null($objectClasses)) {
            throw new \Exception('LDAP objects must have the object classes to be created.');
        }

        /** @var LdapObject $model */
        $model = $this->newEloquentModel();
        $model->dn = $dn;
        $model->name = $this->model->getCreatableRdn();
        $model->guid = Uuid::uuid4()->toString();
        $model->domain = $this->model->getConnectionName();
        $model->save();

        foreach ($objectClasses as $objectClass) {
            $model->classes()->create(['name' => $objectClass]);
        }

        foreach ($attributes as $name => $values) {
            $model->attributes()->create([
                'name' => $name,
                'values' => $values,
            ]);
        }

        return true;
    }

    public function update($dn, array $modifications)
    {
        /** @var LdapObject $model */
        $model = $this->findEloquentModelByDn($dn);

        if (! $model) {
            return false;
        }

        foreach ($modifications as $modification) {
            $name = $modification[BatchModification::KEY_ATTRIB];
            $type = $modification[BatchModification::KEY_MODTYPE];
            $values = $modification[BatchModification::KEY_VALUES];

            $attribute = $model->attributes()->firstOrNew(['name' => $name]);

            if ($type == LDAP_MODIFY_BATCH_REMOVE_ALL && $attribute->exists) {
                $attribute->delete();

                continue;
            }

            if ($type == LDAP_MODIFY_BATCH_REMOVE) {
            }

            if ($type == LDAP_MODIFY_BATCH_ADD) {
                $attribute->values = array_merge($attribute->values ?? [], $values);
            }

            $attribute->save();

            switch ($type) {
                case LDAP_MODIFY_BATCH_REMOVE_ALL:
                    if ($attribute->exists) {
                        $attribute->delete();
                    }
                    break;
                case LDAP_MODIFY_BATCH_REMOVE:
                    foreach ($modification['values'] as $value) {
                    }
                    break;
                case LDAP_MODIFY_BATCH_REPLACE:
                    $attribute->values = $values;
                case LDAP_MODIFY_BATCH_ADD:

                    break;
            }
        }

        return true;
    }

    public function rename($dn, $rdn, $newParentDn, $deleteOldRdn = true)
    {
        //
    }

    public function delete($dn)
    {
        $database = $this->findEloquentModelByDn($dn);

        return $database ? $database->delete() : false;
    }

    public function deleteAttributes($dn, array $attributes)
    {
        //
    }

    public function escape($value, $ignore = '', $flags = 0)
    {
        // Don't escape values.
        return $value;
    }

    public function run($query)
    {
        if (count($this->filters['raw']) > 0) {
            // throw exception, raw filters not supported for DB LDAP.
        }

        $databaseQuery = $this->newEloquentQuery();

        $this->applyFiltersToDatabaseQuery($databaseQuery);

        return $databaseQuery->get();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    protected function applyFiltersToDatabaseQuery($query)
    {
        foreach ($this->filters['and'] as $filter) {
            $this->applyWhereFilterToDatabaseQuery($query, $filter['field'], $filter['operator'], $filter['value']);
        }

        foreach ($this->filters['or'] as $filter) {
            $this->applyWhereFilterToDatabaseQuery($query, $filter['field'], $filter['operator'], $filter['value'], 'or');
        }
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $field
     * @param string                                $operator
     * @param array|null                            $value
     * @param string                                $boolean
     */
    protected function applyWhereFilterToDatabaseQuery($query, $field, $operator, $value = null, $boolean = 'and')
    {
        $relation = $field == 'objectclass' ? 'classes' : 'attributes';

        $query->whereHas($relation, function ($q) use ($relation, $field, $operator, $value) {
            if ($relation == 'classes') {
                $q->where('name', '=', $value);
            } else {
                $where = ['name' => $field];

                if ($operator == '*') {
                    $where['value'] = $value;
                }

                $q->where($where);
            }
        })->with(['classes', 'attributes']);
    }

    protected function parse($resource)
    {
        return $resource->toArray();
    }

    protected function process(array $results)
    {
        return $this->model->newCollection($results)->transform(function ($attributes) {
            $transformedAttributes = collect(Arr::pull($attributes, 'attributes'))->mapWithKeys(function ($values, $key) {
                return [$values['name'] => $values['values']];
            })->toArray();

            $dn = Arr::pull($attributes, 'dn');

            return $this->model->newInstance()
                ->setDn($dn)
                ->setRawAttributes($transformedAttributes);
        });
    }
}
