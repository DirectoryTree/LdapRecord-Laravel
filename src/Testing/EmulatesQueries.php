<?php

namespace LdapRecord\Laravel\Testing;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use LdapRecord\Connection;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\BatchModification;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use Ramsey\Uuid\Uuid;

trait EmulatesQueries
{
    /**
     * The LDAP attributes to include in results due to a 'select' statement.
     *
     * @var array
     */
    protected $only = [];

    /**
     * The underlying database query.
     *
     * @var \Illuminate\Database\Eloquent\Builder
     */
    protected $query;

    /**
     * The nested query state.
     *
     * @var string|null
     */
    protected $nestedState;

    /**
     * Constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);

        $this->query = $this->newEloquentQuery();
    }

    /**
     * Create a new Eloquent query from the configured model.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function newEloquentQuery()
    {
        return $this->newEloquentModel()->newQuery();
    }

    /**
     * Create a new instance of the configured model.
     *
     * @return LdapObject
     */
    public function newEloquentModel()
    {
        return app(LdapDatabaseManager::class)->createModel(
            $this->getConnection()->name()
        );
    }

    /**
     * Set the underlying Eloquent query builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return $this
     */
    public function setEloquentQuery($query)
    {
        $this->query = $query;

        return $this;
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
     * Create a new nested query builder with the given state.
     *
     * @param Closure|null $closure
     * @param string       $state
     *
     * @return Builder
     */
    public function newNestedInstance(Closure $closure = null, $state = 'and')
    {
        $query = $this->newInstance()->nested()->setNestedQueryState($state);

        if ($closure) {
            $closure($query);
        }

        // Here we will merge the constraints from the nested
        // query instance to make sure any bindings are
        // carried over that were applied to it.
        $this->query->mergeConstraintsFrom(
            $query->getEloquentQuery()
        );

        return $query;
    }

    /**
     * @inheritdoc
     */
    public function clearFilters()
    {
        // When clear filters is called, we must clear the
        // current Eloquent query instance with it to
        // ensure no bindings are carried over.
        $this->query = $this->newEloquentQuery();

        return parent::clearFilters();
    }

    /**
     * Set the nested query state.
     *
     * @param string $state
     *
     * @return $this
     */
    public function setNestedQueryState($state)
    {
        $this->nestedState = $state;

        return $this;
    }

    /**
     * @inheritdoc
     */
    public function orFilter(Closure $closure)
    {
        $query = $this->newNestedInstance($closure, 'or');

        return $this->rawFilter(
            $this->grammar->compileOr($query->getQuery())
        );
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
        return $this->newEloquentQuery()->where('dn', 'like', $dn)->first();
    }

    /**
     * Find the Eloquent model by guid.
     *
     * @param string $guid
     *
     * @return LdapObject|null
     */
    public function findEloquentModelByGuid($guid)
    {
        return $this->newEloquentQuery()->where('guid', '=', $guid)->first();
    }

    /**
     * @inheritdoc
     */
    public function addFilter($type, array $bindings)
    {
        $relationMethod = $this->determineRelationMethod($type, $bindings);

        // If the relation method is "not has", we will flip it
        // to a "has" filter and change the relation method
        // so database results are retrieved properly.
        if (in_array($relationMethod, ['whereDoesntHave', 'orWhereDoesntHave'])) {
            $bindings['operator'] = '*';
        }

        $this->query->{$relationMethod}('attributes', function ($query) use ($bindings) {
            $this->addFilterToDatabaseQuery(
                $query,
                $this->normalizeAttributeName($bindings['field']),
                $bindings['operator'],
                $bindings['value']
            );
        });

        return parent::addFilter($type, $bindings);
    }

    /**
     * Determine the relationship method to use for the given bindings.
     *
     * @param string $type
     * @param array  $bindings
     *
     * @return string
     */
    protected function determineRelationMethod($type, array $bindings)
    {
        $method = $bindings['operator'] == '!*' ? 'whereDoesntHave' : 'whereHas';

        if ($type == 'or') {
            $method = 'orWhereHas';
        }

        // We're doing some trickery here for compatibility with nested LDAP filters. The
        // nested state is used to determine if the query being nested is an "and" or
        // "or" which will give us proper results when changing the relation method.
        if (
            $this->nested
            && $this->nestedState = 'or'
            && $this->fieldIsUsedMultipleTimes($type, $bindings['field'])
        ) {
            $method = $method == 'whereDoesntHave' ?
                'orWhereDoesntHave' :
                'orWhereHas';
        }

        return $method;
    }

    /**
     * Adds an LDAP "where" filter to the underlying Eloquent builder.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $field
     * @param string                                $operator
     * @param string|null                           $value
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function addFilterToDatabaseQuery($query, $field, $operator, $value)
    {
        switch ($operator) {
            case '*':
                return $query->where('name', '=', $field);
            case '!*':
                return $query->where('name', '!=', $field);
            case '=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'like', $value);
                    });
            case '!':
                // Fallthrough.
            case '!=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'not like', $value);
                    });
            case'>=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', '>=', $value);
                    });
            case'<=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', '<=', $value);
                    });
            case'~=':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'like', "%$value%");
                    });
            case'starts_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'like', "$value%");
                    });
            case'not_starts_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'not like', "$value%");
                    });
            case'ends_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'like', "%$value");
                    });
            case'not_ends_with':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'not like', "%$value");
                    });
            case'contains':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'like', "%$value%");
                    });
            case'not_contains':
                return $query->where('name', '=', $field)
                    ->whereHas('values', function ($q) use ($value) {
                        $q->where('value', 'not like', "%$value%");
                    });
        }
    }

    /**
     * Determine if a certain field is used multiple times in a query.
     *
     * @param string $type
     * @param string $field
     *
     * @return bool
     */
    protected function fieldIsUsedMultipleTimes($type, $field)
    {
        return collect($this->filters[$type])->where('field', '=', $field)->isNotEmpty();
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

        $attribute = $model->attributes()->firstOrCreate([
            'name' => $name,
        ]);

        switch ($type) {
            case LDAP_MODIFY_BATCH_ADD:
                foreach ($values as $value) {
                    $attribute->values()->create(['value' => $value]);
                }
                break;
            case LDAP_MODIFY_BATCH_REPLACE:
                $attribute->values()->delete();

                foreach ($values as $value) {
                    $attribute->values()->create(['value' => $value]);
                }
                break;
            case LDAP_MODIFY_BATCH_REMOVE:
                $attribute->values()->whereIn('value', $values)->delete();
                break;
            case LDAP_MODIFY_BATCH_REMOVE_ALL:
                $attribute->delete();
                break;
        }
    }

    /**
     * @inheritdoc
     */
    public function findOrFail($dn, $columns = ['*'])
    {
        if (! $database = $this->findEloquentModelByDn($dn)) {
            return;
        }

        return $this->getFirstRecordFromResult(
            $this->process([$this->getArrayableResult($database)])
        );
    }

    /**
     * @inheritdoc
     */
    public function findByGuidOrFail($guid, $columns = ['*'])
    {
        if (! $database = $this->findEloquentModelByGuid($guid)) {
            return;
        }

        return $this->getFirstRecordFromResult(
            $this->process([$this->getArrayableResult($database)])
        );
    }

    /**
     * Get the database record as an array.
     *
     * @param Model|array $database
     *
     * @return array
     */
    protected function getArrayableResult($database)
    {
        return $database instanceof Model ? $database->toArray() : $database;
    }

    /**
     * Get the first record from a result.
     *
     * @param Collection|array $result
     *
     * @return mixed
     */
    protected function getFirstRecordFromResult($result)
    {
        return $result instanceof Collection ? $result->first() : reset($result);
    }

    /**
     * @inheritdoc
     */
    public function insert($dn, array $attributes)
    {
        if (! Arr::get($attributes, 'objectclass')) {
            throw new Exception('LDAP objects must have object classes to be created.');
        }

        $model = $this->applyObjectAttributesToEloquent(
            $this->newEloquentModel(),
            $dn,
            $attributes
        );

        $model->save();

        foreach ($attributes as $name => $values) {
            $attribute = $model->attributes()->create([
                'name' => $this->normalizeAttributeName($name),
            ]);

            foreach ((array) $values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        return true;
    }

    /**
     * Apply the LDAP objects attributes to the Eloquent model.
     *
     * @param LdapObject $model
     * @param string     $dn
     * @param array      $attributes
     *
     * @return LdapObject
     */
    protected function applyObjectAttributesToEloquent(LdapObject $model, $dn, $attributes)
    {
        $dn = new DistinguishedName($dn);

        $model->dn = $dn->get();
        $model->name = $dn->relative();
        $model->parent_dn = $dn->parent();
        $model->domain = $this->connection->name();

        $guidKey = $this->determineGuidKey() ?? $this->determineGuidKeyFromAttributes($attributes);

        $model->guid_key = $guidKey;
        $model->guid = $this->pullGuidFromAttributes($guidKey, $attributes) ?? Uuid::uuid4()->toString();

        return $model;
    }

    /**
     * @inheritdoc
     */
    public function updateAttributes($dn, array $attributes)
    {
        if (! $model = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        foreach ($attributes as $name => $values) {
            $attribute = $model->attributes()->firstOrCreate([
                'name' => $this->normalizeAttributeName($name),
            ]);

            $attribute->values()->delete();

            foreach ((array) $values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        return true;
    }

    /**
     * Normalize the attribute name.
     *
     * @param string $field
     *
     * @return string
     */
    protected function normalizeAttributeName($field)
    {
        return strtolower($field);
    }

    /**
     * Pull and return the GUID value from the given attributes.
     *
     * @param string|null $key
     * @param array       $attributes
     *
     * @return string
     */
    protected function pullGuidFromAttributes($key, &$attributes)
    {
        if (! $key) {
            return;
        }

        if (! Arr::has($attributes, $key)) {
            return;
        }

        return Arr::first(
            Arr::pull($attributes, $key)
        );
    }

    /**
     * Attempt to determine the GUID attribute key.
     *
     * @return string|null
     */
    protected function determineGuidKey()
    {
        return property_exists($this, 'model') ? $this->model->getGuidKey() : null;
    }

    /**
     * Determine the guid key from the given object attributes.
     *
     * @param array $attributes
     *
     * @return string|null
     */
    protected function determineGuidKeyFromAttributes($attributes)
    {
        foreach ($attributes as $attribute => $values) {
            if (Guid::isValid($this->attributeValueIsGuid($values))) {
                return $attribute;
            }
        }
    }

    /**
     * Determine if the given attribute value is a GUID.
     *
     * @param array|string $value
     *
     * @return bool
     */
    protected function attributeValueIsGuid($value)
    {
        return Guid::isValid(
            is_array($value) ? reset($value) : $value
        );
    }

    /**
     * @inheritdoc
     */
    public function rename($dn, $rdn, $newParentDn, $deleteOldRdn = true)
    {
        $database = $this->findEloquentModelByDn($dn);

        if ($database) {
            $database->name = $rdn;
            $database->dn = implode(',', [$rdn, $newParentDn]);
            $database->parent_dn = $newParentDn;

            return $database->save();
        }

        return false;
    }

    /**
     * @inheritdoc
     */
    public function delete($dn)
    {
        if (! $database = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        return $database->delete();
    }

    /**
     * @inheritdoc
     */
    public function escape($value, $ignore = '', $flags = 0)
    {
        return new UnescapedValue($value);
    }

    /**
     * @inheritdoc
     */
    public function paginate($pageSize = 1000, $isCritical = false)
    {
        return $this->get();
    }

    /**
     * @inheritdoc
     */
    public function run($query)
    {
        if ($this->limit > 0) {
            $this->query->limit($this->limit);
        }

        if (! in_array('*', $this->columns)) {
            $this->only = $this->columns;
        }

        switch ($this->type) {
            case 'read':
                // Emulate performing a single "read" operation.
                $this->query->where('dn', '=', $this->dn);
                break;
            case 'listing':
                // Emulate performing a directory "listing" operation.
                $this->query->where('parent_dn', '=', $this->dn);
                break;
            case 'search':
                // Emulate performing a global directory "search" operation.
                $this->query->where('dn', 'like', "%{$this->dn}");
                break;
        }

        return $this->query->get();
    }

    /**
     * Convert the eloquent collection into an array.
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
     * Transform the database attributes into a single array.
     *
     * @param mixed $attributes
     *
     * @return array
     */
    protected function transform($attributes)
    {
        return collect(Arr::pull($attributes, 'attributes'))->mapWithKeys(function ($attribute) {
            return [$attribute['name'] => collect($attribute['values'])->map->value->toArray()];
        })->when(! empty($this->only), function ($attributes) {
            return $attributes->filter(function ($value, $key) {
                return in_array($key, $this->only);
            });
        })->tap(function () {
            $this->only = [];
        })->toArray();
    }
}
