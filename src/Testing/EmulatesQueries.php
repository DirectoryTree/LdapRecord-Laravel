<?php

namespace LdapRecord\Laravel\Testing;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use LdapRecord\Connection;
use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\BatchModification;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Query\Collection;
use Ramsey\Uuid\Uuid;

trait EmulatesQueries
{
    /**
     * The underlying database query.
     */
    protected EloquentBuilder $query;

    /**
     * The LDAP attributes to include in results due to a 'select' statement.
     */
    protected array $only = [];

    /**
     * The nested query state.
     */
    protected ?string $nestedState = null;

    /**
     * Constructor.
     */
    public function __construct(Connection $connection)
    {
        parent::__construct($connection);

        $this->query = $this->newEloquentQuery();
    }

    /**
     * Create a new Eloquent query from the configured model.
     */
    public function newEloquentQuery(): EloquentBuilder
    {
        return $this->newEloquentModel()->newQuery();
    }

    /**
     * Create a new instance of the configured model.
     */
    public function newEloquentModel(): LdapObject
    {
        return app(LdapDatabaseManager::class)->createModel(
            $this->getConnection()->name()
        );
    }

    /**
     * Set the underlying Eloquent query builder.
     */
    public function setEloquentQuery(EloquentBuilder $query): static
    {
        $this->query = $query;

        return $this;
    }

    /**
     * Get the underlying Eloquent query builder.
     */
    public function getEloquentQuery(): EloquentBuilder
    {
        return $this->query;
    }

    /**
     * Create a new nested query builder with the given state.
     */
    public function newNestedInstance(?Closure $closure = null, string $state = 'and'): static
    {
        $query = $this->newInstance()->nested()->setNestedQueryState($state);

        if ($closure) {
            $this->query->where(function (EloquentBuilder $nested) use ($closure, $query) {
                $closure($query->setEloquentQuery($nested));
            });
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function clearFilters(): static
    {
        // When clear filters is called, we must clear the
        // current Eloquent query instance with it to
        // ensure no bindings are carried over.
        $this->query = $this->newEloquentQuery();

        return parent::clearFilters();
    }

    /**
     * Set the nested query state.
     */
    public function setNestedQueryState(string $state): static
    {
        $this->nestedState = $state;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function orFilter(Closure $closure): static
    {
        $query = $this->newNestedInstance($closure, 'or');

        return $this->rawFilter(
            $this->grammar->compileOr($query->getQuery())
        );
    }

    /**
     * Find the Eloquent model by distinguished name.
     */
    public function findEloquentModelByDn(string $dn): ?LdapObject
    {
        return $this->newEloquentModel()->findByDn($dn, $this->getConnection()->name());
    }

    /**
     * Find the Eloquent model by guid.
     */
    public function findEloquentModelByGuid(string $guid): ?LdapObject
    {
        return $this->newEloquentModel()->findByGuid($guid, $this->getConnection()->name());
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter($type, array $bindings): static
    {
        $relationMethod = $this->determineRelationMethod($type, $bindings);

        $operator = $bindings['operator'];

        // If the relation method is "not has", we will flip it
        // to a "has" filter and change the relation method
        // so database results are retrieved properly.
        if (in_array($relationMethod, ['whereDoesntHave', 'orWhereDoesntHave'])) {
            $operator = '*';
        }

        $this->query->{$relationMethod}('attributes', function ($query) use ($bindings, $operator) {
            $field = $this->normalizeAttributeName($bindings['field']);

            $this->addFilterToDatabaseQuery(
                $query,
                $field,
                $operator,
                $bindings['value']
            );
        });

        return parent::addFilter($type, $bindings);
    }

    /**
     * Determine the relationship method to use for the given bindings.
     */
    protected function determineRelationMethod(string $type, array $bindings): string
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
            && $this->nestedState === 'or'
            && $this->fieldIsUsedMultipleTimes($type, $bindings['field'])
        ) {
            $method = $method == 'whereDoesntHave'
                ? 'orWhereDoesntHave'
                : 'orWhereHas';
        }

        return $method;
    }

    /**
     * Adds an LDAP "where" filter to the underlying Eloquent builder.
     */
    protected function addFilterToDatabaseQuery(EloquentBuilder $query, string $field, string $operator, ?string $value): void
    {
        match ($operator) {
            '*' => $query->where('name', '=', $field),

            '!*' => $query->where('name', '!=', $field),

            '=' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'like', $value);
                }),

            '!', '!=' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'not like', $value);
                }),

            '>=' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', '>=', $value);
                }),

            '<=' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', '<=', $value);
                }),

            '~=' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'like', "%$value%");
                }),

            'starts_with' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'like', "$value%");
                }),

            'not_starts_with' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'not like', "$value%");
                }),

            'ends_with' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'like', "%$value");
                }),

            'not_ends_with' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'not like', "%$value");
                }),

            'contains' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'like', "%$value%");
                }),

            'not_contains' => $query->where('name', '=', $field)
                ->whereHas('values', function ($q) use ($value) {
                    $q->where('value', 'not like', "%$value%");
                }),
        };
    }

    /**
     * Determine if a certain field is used multiple times in a query.
     */
    protected function fieldIsUsedMultipleTimes(string $type, string $field): bool
    {
        return collect($this->filters[$type])->where('field', '=', $field)->isNotEmpty();
    }

    /**
     * Applies the batch modification to the given model.
     */
    protected function applyBatchModificationToModel(Model $model, array $modification): void
    {
        $name = $modification[BatchModification::KEY_ATTRIB];
        $type = $modification[BatchModification::KEY_MODTYPE];
        $values = $modification[BatchModification::KEY_VALUES] ?? [];

        /** @var LdapObjectAttribute $attribute */
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
                $attribute->values()->each(
                    fn (LdapObjectAttributeValue $value) => $value->delete()
                );

                foreach ($values as $value) {
                    $attribute->values()->create(['value' => $value]);
                }
                break;
            case LDAP_MODIFY_BATCH_REMOVE:
                $attribute->values()->whereIn('value', $values)->each(
                    fn (LdapObjectAttributeValue $value) => $value->delete()
                );
                break;
            case LDAP_MODIFY_BATCH_REMOVE_ALL:
                $attribute->delete();
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function findOrFail(string $dn, array|string $columns = ['*']): LdapRecord|array
    {
        if (! $database = $this->findEloquentModelByDn($dn)) {
            $this->throwNotFoundException($this->getUnescapedQuery(), $dn);
        }

        return $this->getFirstRecordFromResult(
            $this->process([$this->getArrayableResult($database)])
        );
    }

    /**
     * {@inheritdoc}
     */
    public function findByGuidOrFail(string $guid, array|string $columns = ['*']): LdapRecord
    {
        if (! $database = $this->findEloquentModelByGuid($guid)) {
            $this->throwNotFoundException($this->getUnescapedQuery(), $this->dn);
        }

        return $this->getFirstRecordFromResult(
            $this->process([$this->getArrayableResult($database)])
        );
    }

    /**
     * Get the database record as an array.
     */
    protected function getArrayableResult(Model|array $database): Model|array
    {
        return $database instanceof Model ? $database->toArray() : $database;
    }

    /**
     * Get the first record from a result.
     */
    protected function getFirstRecordFromResult(Collection|array $result)
    {
        return $result instanceof Collection ? $result->first() : reset($result);
    }

    /**
     * {@inheritdoc}
     */
    public function insertAndGetDn($dn, array $attributes): string|false
    {
        $dn = $this->substituteBaseDn($dn);

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
            /** @var LdapObjectAttribute $attribute */
            $attribute = $model->attributes()->create([
                'name' => $this->normalizeAttributeName($name),
            ]);

            foreach ((array) $values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        return $dn;
    }

    /**
     * Apply the LDAP objects attributes to the Eloquent model.
     */
    protected function applyObjectAttributesToEloquent(LdapObject $model, string $dn, array $attributes): LdapObject
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
     * {@inheritdoc}
     */
    public function updateAttributes($dn, array $attributes): bool
    {
        if (! $model = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        foreach ($attributes as $name => $values) {
            /** @var LdapObjectAttribute $attribute */
            $attribute = $model->attributes()->firstOrCreate([
                'name' => $this->normalizeAttributeName($name),
            ]);

            $attribute->values()->each(
                fn (LdapObjectAttributeValue $value) => $value->delete()
            );

            foreach ((array) $values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        return true;
    }

    /**
     * Normalize the attribute name.
     */
    protected function normalizeAttributeName(string $field): string
    {
        return strtolower($field);
    }

    /**
     * Pull and return the GUID value from the given attributes.
     */
    protected function pullGuidFromAttributes(?string $key, array &$attributes): ?string
    {
        if (! $key) {
            return null;
        }

        if (! Arr::has($attributes, $key)) {
            return null;
        }

        return Arr::first(
            Arr::pull($attributes, $key)
        );
    }

    /**
     * Attempt to determine the GUID attribute key.
     */
    protected function determineGuidKey(): ?string
    {
        return property_exists($this, 'model') ? $this->model->getGuidKey() : null;
    }

    /**
     * Determine the guid key from the given object attributes.
     */
    protected function determineGuidKeyFromAttributes(array $attributes): ?string
    {
        foreach ($attributes as $attribute => $values) {
            if (Guid::isValid($this->attributeValueIsGuid($values))) {
                return $attribute;
            }
        }

        return null;
    }

    /**
     * Determine if the given attribute value is a GUID.
     */
    protected function attributeValueIsGuid(array|string $value): bool
    {
        return Guid::isValid(
            is_array($value) ? reset($value) : $value
        );
    }

    /**
     * {@inheritdoc}
     */
    public function renameAndGetDn($dn, $rdn, $newParentDn, $deleteOldRdn = true): string|false
    {
        $newParentDn = $this->substituteBaseDn($newParentDn);

        $database = $this->findEloquentModelByDn($dn);

        if ($database) {
            $database->name = $rdn;
            $database->dn = implode(',', [$rdn, $newParentDn]);
            $database->parent_dn = $newParentDn;

            $database->save();

            return $database->dn;
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($dn): bool
    {
        if (! $database = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        return $database->delete();
    }

    /**
     * {@inheritdoc}
     */
    public function escape(mixed $value = null, string $ignore = '', int $flags = 0): UnescapedValue
    {
        return new UnescapedValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate(int $pageSize = 1000, bool $isCritical = false): Collection|array
    {
        return $this->get();
    }

    /**
     * {@inheritdoc}
     */
    public function run(string $filter): mixed
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
            case 'list':
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
     * @param  \Illuminate\Database\Eloquent\Collection  $resource
     */
    public function parse(mixed $resource): array
    {
        return $resource->toArray();
    }

    /**
     * Transform the database attributes into a single array.
     */
    protected function transform(array $attributes): array
    {
        return collect(Arr::pull($attributes, 'attributes'))->mapWithKeys(function ($attribute) {
            return [$attribute['name'] => collect($attribute['values'])->map->value->all()];
        })->when(! empty($this->only), function ($attributes) {
            return $attributes->filter(function ($value, $key) {
                return in_array($key, $this->only);
            });
        })->tap(function () {
            $this->only = [];
        })->all();
    }
}
