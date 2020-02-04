<?php

namespace LdapRecord\Laravel\Testing;

use Closure;
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
        $this->grammar = new EloquentLdapGrammar($this->query);
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

    public function newInstance($baseDn = null)
    {
        return (new self($this->connection))->setModel($this->model);
    }

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
        return $this->query
            ->where('dn', '=', $dn)
            ->with(['attributes'])
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
        if ($this->limit > 0) {
            $this->query->limit($this->limit);
        }

        return $this->query->get();
    }

    public function orFilter(Closure $closure)
    {
        $query = $this->newNestedInstance($closure);

        $return = $this->rawFilter(
            $this->grammar->compileOr($query->getQuery())
        );

        $this->query->mergeConstraintsFrom($query->getEloquentQuery());

        return $return;
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
