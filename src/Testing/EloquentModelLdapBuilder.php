<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Support\Arr;
use LdapRecord\Connection;
use LdapRecord\Models\BatchModification;
use LdapRecord\Query\Model\Builder;
use Ramsey\Uuid\Uuid;

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
     * {@inheritDoc}
     */
    public function newInstance($baseDn = null)
    {
        return (new self($this->connection))->setModel($this->model);
    }

    /**
     * Get the underlying Eloquent query builder.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function getEloquentQuery()
    {
        return $this->query;
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
        return $this->query->where('dn', '=', $dn)->first();
    }

    /**
     * {@inheritDoc}
     */
    public function findOrFail($dn, $columns = ['*'])
    {
        if ($database = $this->findEloquentModelByDn($dn)) {
            return $this->transformDatabaseAttributesToLdapModel($database->toArray());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function insert($dn, array $attributes)
    {
        if (Arr::get($attributes, 'objectclass') == null) {
            throw new \Exception('LDAP objects must have the object classes to be created.');
        }

        /** @var LdapObject $model */
        $model = $this->newEloquentModel();
        $model->dn = $dn;
        $model->name = $this->model->getCreatableRdn();
        $model->guid = Uuid::uuid4()->toString();
        $model->domain = $this->model->getConnectionName();
        $model->save();

        foreach ($attributes as $name => $values) {
            $attribute = $model->attributes()->create(['name' => $name]);

            foreach ($values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
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
     * Applies the batch modification to the given model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     * @param array                               $modification
     *
     * @return void
     */
    protected function applyBatchModificationToModel($model, array $modification)
    {
        $name = $modification[BatchModification::KEY_ATTRIB];
        $type = $modification[BatchModification::KEY_MODTYPE];
        $values = $modification[BatchModification::KEY_VALUES] ?? [];

        $attribute = $model->attributes()->firstOrCreate(['name' => $name]);

        if ($type == LDAP_MODIFY_BATCH_REMOVE_ALL) {
            $attribute->delete();

            return;
        } elseif ($type == LDAP_MODIFY_BATCH_REMOVE) {
            $attribute->values()->whereIn('value', $values)->delete();
        } elseif ($type == LDAP_MODIFY_BATCH_ADD) {
            foreach ($values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        } elseif ($type == LDAP_MODIFY_BATCH_REPLACE) {
            $attribute->values()->delete();

            foreach ($values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        $attribute->save();
    }

    /**
     * {@inheritDoc}
     */
    public function rename($dn, $rdn, $newParentDn, $deleteOldRdn = true)
    {
        //
    }

    /**
     * {@inheritDoc}
     */
    public function delete($dn)
    {
        $database = $this->findEloquentModelByDn($dn);

        return $database ? $database->delete() : false;
    }

    /**
     * {@inheritDoc}
     */
    public function deleteAttributes($dn, array $attributes)
    {
        if ($database = $this->findEloquentModelByDn($dn)) {
            $database->attributes()->whereIn('name', $attributes)->delete();

            return true;
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function escape($value, $ignore = '', $flags = 0)
    {
        // Don't escape values.
        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function run($query)
    {
        if ($this->limit > 0) {
            $this->query->limit($this->limit);
        }

        $this->applyFiltersToQuery();

        return $this->query->get();
    }

    /**
     * Applies filters to the underlying Eloquent database query.
     */
    protected function applyFiltersToQuery()
    {
        $this->applyAndFiltersToQuery();
    }

    /**
     * Applies "and" filters to the underlying Eloquent database query.
     */
    protected function applyAndFiltersToQuery()
    {
        $filters = $this->filters['and'];

        if (count($filters) > 0) {
            foreach ($this->filters['and'] as $filter) {
                $relationMethod = 'whereHas';

                if($filter['operator'] == '!*') {
                    $relationMethod = 'whereDoesntHave';
                    $filter['operator'] = '*';
                }

                $this->query->{$relationMethod}('attributes', function ($query) use ($filter) {
                    $this->addFilterToQuery($query, $filter['field'], $filter['operator'], $filter['value']);
                });
            }
        }
    }

    /**
     * Adds an LDAP "Where" filter to the underlying Eloquent builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param $field
     * @param $operator
     * @param $value
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addFilterToQuery($query, $field, $operator, $value)
    {
        switch ($operator) {
            case '*':
                return $query->where('name', '=', $field);
            case '!*':
                return $query->where('name', '!=', $field);
            case '=':
                return $query->where('name', $operator, $field)
                        ->whereHas('values', function ($q) use ($operator, $value) {
                            $q->where('value', $operator, $value);
                        });
            case '!':
                // Fallthrough.
            case '!=':
                return $query->where('name', '=', $field)
                        ->whereHas('values', function ($q) use ($operator, $value) {
                            $q->where('value', '!=', $value);
                        });
            case'>=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', '>=', $value);
                    });
            case'<=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', '<=', $value);
                    });
            case'~=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', 'like', "%$value%");
                    });
            case'starts_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', 'like', "$value%");
                    });
            case'not_starts_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', 'not like', "$value%");
                    });
            case'ends_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', 'like', "%$value");
                    });
            case'not_ends_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', 'not like', "%$value");
                    });
            case'contains':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', 'like', "%$value%");
                    });
            case'not_contains':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($operator, $value) {
                        $q->where('value', 'not like', "%$value%");
                    });
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function parse($resource)
    {
        return $resource->toArray();
    }

    /**
     * {@inheritDoc}
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
        $dn = Arr::pull($attributes, 'dn');

        $transformedAttributes = collect(Arr::pull($attributes, 'attributes'))->mapWithKeys(function ($attribute) {
            $values = collect($attribute['values'])->map->value->toArray();

            return [$attribute['name'] => $values];
        })->toArray();

        return $this->model->newInstance()
            ->setDn($dn)
            ->setRawAttributes($transformedAttributes);
    }
}
