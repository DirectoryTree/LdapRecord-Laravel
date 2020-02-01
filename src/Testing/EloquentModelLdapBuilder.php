<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Support\Arr;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;
use Ramsey\Uuid\Uuid;

class EloquentModelLdapBuilder extends Builder
{
    public function delete($dn)
    {
        EloquentFactory::createModel()->where('dn', '=', $dn)->delete();

        return true;
    }

    public function deleteAttributes($dn, array $attributes)
    {
        //
    }

    public function findOrFail($dn, $columns = ['*'])
    {
        if ($database = EloquentFactory::createModel()->where('dn', '=', $dn)->first()) {
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
        $model = EloquentFactory::createModel();
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

    public function rename($dn, $rdn, $newParentDn, $deleteOldRdn = true)
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

        $databaseQuery = EloquentFactory::createModel()->newQuery();

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
     * @param $field
     * @param $operator
     * @param null $value
     * @param string $boolean
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
        })->with($relation);
    }

    protected function parse($resource)
    {
        return $resource->toArray();
    }

    protected function process(array $results)
    {
        // TODO: Convert the returned models.
        return $results;
    }
}
