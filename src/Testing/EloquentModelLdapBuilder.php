<?php

namespace LdapRecord\Laravel\Testing;

use Ramsey\Uuid\Uuid;
use Illuminate\Support\Arr;
use LdapRecord\Connection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\BatchModification;

class EloquentModelLdapBuilder extends Builder
{
    /**
     * The underlying database query.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    public function __construct(Connection $connection)
    {
        parent::__construct($connection);

        $this->query = $this->newEloquentQuery();
    }

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
        return $this->query
            ->where('dn', '=', $dn)
            ->with(['classes', 'attributes'])
            ->first();
    }

    public function findOrFail($dn, $columns = ['*'])
    {
        if ($database = $this->findEloquentModelByDn($dn)) {
            return $this->transformDatabaseAttributesToLdapModel($database->toArray());
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
            $values = $modification[BatchModification::KEY_VALUES] ?? [];

            $attribute = $model->attributes()->firstOrNew(['name' => $name]);

            if ($type == LDAP_MODIFY_BATCH_REMOVE_ALL && $attribute->exists) {
                $attribute->delete();
                continue;
            } elseif ($type == LDAP_MODIFY_BATCH_REMOVE) {
                $attribute->values = array_diff($attribute->values ?? [], $values);
            } elseif ($type == LDAP_MODIFY_BATCH_ADD) {
                $attribute->values = array_merge($attribute->values ?? [], $values);
            } elseif ($type == LDAP_MODIFY_BATCH_REPLACE) {
                $attribute->values = $values;
            }

            $attribute->save();
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
        if ($database = $this->findEloquentModelByDn($dn)) {
            $database->attributes()->whereIn('name', $attributes)->delete();

            return true;
        }

        return false;
    }

    public function escape($value, $ignore = '', $flags = 0)
    {
        // Don't escape values.
        return $value;
    }

    public function run($query)
    {
        return $this->query->limit($this->limit)->get();
    }

    public function addFilter($type, array $bindings)
    {
        if ($type == 'raw') {
            throw new \Exception('Raw filters are not supported with the EloquentModelBuilder.');
        }

        $this->applyWhereFilterToDatabaseQuery(
            $this->query,
            $bindings['field'],
            $bindings['operator'],
            $bindings['value'],
            $type
        );

        return parent::addFilter($type, $bindings);
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
        $transformedAttributes = collect(Arr::pull($attributes, 'attributes'))->mapWithKeys(function ($values, $key) {
            return [$values['name'] => $values['values']];
        })->toArray();

        $dn = Arr::pull($attributes, 'dn');

        return $this->model->newInstance()
            ->setDn($dn)
            ->setRawAttributes($transformedAttributes);
    }
}
