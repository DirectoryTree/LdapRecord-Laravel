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
use LdapRecord\Query\Collection;
use LdapRecord\Query\Filter\ApproximatelyEquals;
use LdapRecord\Query\Filter\ConditionFilter;
use LdapRecord\Query\Filter\Contains;
use LdapRecord\Query\Filter\EndsWith;
use LdapRecord\Query\Filter\Equals;
use LdapRecord\Query\Filter\Filter;
use LdapRecord\Query\Filter\GreaterThanOrEquals;
use LdapRecord\Query\Filter\GroupFilter;
use LdapRecord\Query\Filter\Has;
use LdapRecord\Query\Filter\LessThanOrEquals;
use LdapRecord\Query\Filter\Not;
use LdapRecord\Query\Filter\StartsWith;
use Ramsey\Uuid\Uuid;

trait EmulatesQueries
{
    /**
     * The LDAP attributes to include in results due to a 'select' statement.
     */
    protected array $only = [];

    /**
     * The underlying database query.
     */
    protected EloquentBuilder $eloquent;

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

        $this->eloquent = $this->newEloquentQuery();
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
        $this->eloquent = $query;

        return $this;
    }

    /**
     * Get the underlying Eloquent query builder.
     */
    public function getEloquentQuery(): EloquentBuilder
    {
        return $this->eloquent;
    }

    /**
     * Create a new nested query builder with the given state.
     */
    public function newNestedInstance(?Closure $closure = null): static
    {
        $query = $this->newInstance()->nested()->setNestedQueryState($this->nestedState ?? 'and');

        if ($closure) {
            $closure($query);
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function clearFilters(): static
    {
        $this->eloquent = $this->newEloquentQuery();

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
     * Apply the LDAP filter to the underlying Eloquent query.
     */
    protected function applyFilterToEloquentQuery(Filter $filter, string $boolean = 'and'): void
    {
        // Handle Not filters by unwrapping them.
        if ($filter instanceof Not) {
            $innerFilter = $filter->getFilter();

            if ($innerFilter instanceof ConditionFilter) {
                $this->applyConditionFilterToEloquentQuery($innerFilter, $boolean, isNegated: true);
            } elseif ($innerFilter instanceof GroupFilter) {
                $this->applyGroupFilterToEloquentQuery($innerFilter, $boolean, isNegated: true);
            }

            return;
        }

        // Handle condition filters (Equals, Has, Contains, etc.)
        if ($filter instanceof ConditionFilter) {
            $this->applyConditionFilterToEloquentQuery($filter, $boolean);

            return;
        }

        // Handle group filters (AndGroup, OrGroup)
        if ($filter instanceof GroupFilter) {
            $this->applyGroupFilterToEloquentQuery($filter, $boolean);
        }
    }

    /**
     * Apply a condition filter to the Eloquent query.
     */
    protected function applyConditionFilterToEloquentQuery(ConditionFilter $filter, string $boolean = 'and', bool $isNegated = false): void
    {
        $operator = $this->getFilterOperatorForDatabase($filter, $isNegated);
        $attribute = $filter->getAttribute();
        $value = $filter->getValue();

        // We handle GUID attribute searches as a special case since the
        // GUID is stored directly on the LdapObject model rather than
        // in the attributes table like all other attribute values are.
        if ($this->isGuidAttribute($attribute) && $value !== null) {
            $this->applyGuidFilterToEloquentQuery($value, $boolean, $isNegated);

            return;
        }

        // Handle ANR (Ambiguous Name Resolution) attribute searches.
        if (strtolower($attribute) === 'anr' && $value !== null) {
            $this->applyAnrFilterToEloquentQuery($value, $boolean, $isNegated);

            return;
        }

        // Determine the relationship method based on the operator and boolean.
        $relationMethod = $this->determineRelationMethod($operator, $boolean, $isNegated);

        // If the relation method is "not has", we will flip it
        // to a "has" filter and change the relation method
        // so database results are retrieved properly.
        if (in_array($relationMethod, ['whereDoesntHave', 'orWhereDoesntHave'])) {
            $operator = '*';
        }

        $this->eloquent->{$relationMethod}('attributes', function ($query) use ($attribute, $operator, $value) {
            $field = $this->normalizeAttributeName($attribute);

            $this->addFilterToDatabaseQuery($query, $field, $operator, $value);
        });
    }

    /**
     * Determine if the given attribute is a GUID attribute.
     */
    protected function isGuidAttribute(string $attribute): bool
    {
        return in_array(strtolower($attribute), ['objectguid', 'entryuuid', 'nsuniqueid', 'ipauniqueid', 'guid']);
    }

    /**
     * Apply a GUID filter to the Eloquent query.
     */
    protected function applyGuidFilterToEloquentQuery(string $value, string $boolean, bool $isNegated): void
    {
        // Try to convert encoded hex GUID to UUID string.
        $guid = GuidValue::toUuid($value);

        $method = $boolean === 'or' ? 'orWhere' : 'where';

        if ($isNegated) {
            $this->eloquent->{$method}('guid', '!=', $guid);
        } else {
            $this->eloquent->{$method}('guid', '=', $guid);
        }
    }

    /**
     * Apply an ANR (Ambiguous Name Resolution) filter to the Eloquent query.
     *
     * ANR searches across multiple common attributes like cn, sn, mail, etc.
     */
    protected function applyAnrFilterToEloquentQuery(string $value, string $boolean, bool $isNegated): void
    {
        $anrAttributes = ['cn', 'sn', 'uid', 'name', 'mail', 'givenname', 'displayname'];

        $method = $boolean === 'or' ? 'orWhere' : 'where';

        $this->eloquent->{$method}(function ($query) use ($anrAttributes, $value, $isNegated) {
            foreach ($anrAttributes as $attribute) {
                $relationMethod = $isNegated ? 'orWhereDoesntHave' : 'orWhereHas';

                $query->{$relationMethod}('attributes', function ($q) use ($attribute, $value, $isNegated) {
                    $q->where('name', $attribute);

                    if ($isNegated) {
                        $q->whereHas('values', function ($vq) use ($value) {
                            $vq->where('value', '=', $value);
                        });
                    } else {
                        $q->whereHas('values', function ($vq) use ($value) {
                            $vq->where('value', '=', $value);
                        });
                    }
                });
            }
        });
    }

    /**
     * Get the database operator for the given filter.
     */
    protected function getFilterOperatorForDatabase(ConditionFilter $filter, bool $isNegated = false): string
    {
        return match (true) {
            $filter instanceof Has => $isNegated ? '!*' : '*',
            $filter instanceof Contains => $isNegated ? 'not_contains' : 'contains',
            $filter instanceof StartsWith => $isNegated ? 'not_starts_with' : 'starts_with',
            $filter instanceof EndsWith => $isNegated ? 'not_ends_with' : 'ends_with',
            $filter instanceof Equals => $isNegated ? '!=' : '=',
            $filter instanceof GreaterThanOrEquals => '>=',
            $filter instanceof LessThanOrEquals => '<=',
            $filter instanceof ApproximatelyEquals => '~=',
            default => $filter->getOperator(),
        };
    }

    /**
     * Apply a group filter to the Eloquent query.
     */
    protected function applyGroupFilterToEloquentQuery(GroupFilter $filter, string $boolean = 'and', bool $isNegated = false): void
    {
        $groupBoolean = $filter->getOperator() === '|' ? 'or' : 'and';

        // Determine the method to use for the outer where clause.
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        // We need to wrap the group's filters in a where clause so that the
        // SQL grouping is correct, particularly for "or" groups where the
        // lack of explicit grouping would result in incorrect precedence.
        $this->eloquent->{$method}(function (EloquentBuilder $query) use ($filter, $groupBoolean, $isNegated) {
            $originalQuery = $this->eloquent;
            $this->eloquent = $query;

            foreach ($filter->getFilters() as $innerFilter) {
                if ($isNegated) {
                    $this->applyFilterToEloquentQuery(new Not($innerFilter), $groupBoolean);
                } else {
                    $this->applyFilterToEloquentQuery($innerFilter, $groupBoolean);
                }
            }

            $this->eloquent = $originalQuery;
        });
    }

    /**
     * Determine the relationship method to use for the given filter.
     */
    protected function determineRelationMethod(string $operator, string $boolean, bool $isNegated): string
    {
        $isNotHas = $operator === '!*' || ($isNegated && $operator === '*');

        $method = $isNotHas ? 'whereDoesntHave' : 'whereHas';

        if ($boolean === 'or') {
            $method = $isNotHas ? 'orWhereDoesntHave' : 'orWhereHas';
        }

        // We're doing some trickery here for compatibility with nested LDAP filters. The
        // nested state is used to determine if the query being nested is an "and" or
        // "or" which will give us proper results when changing the relation method.
        if ($this->nested && $this->nestedState === 'or') {
            $method = $method === 'whereDoesntHave'
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
    public function findOrFail(string $dn, array|string $selects = ['*']): array
    {
        if (! $database = $this->findEloquentModelByDn($dn)) {
            $this->throwNotFoundException($this->getUnescapedQuery(), $dn);
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
            $normalizedName = $this->normalizeAttributeName($name);

            /** @var LdapObjectAttribute|null $attribute */
            $attribute = $model->attributes()->where('name', $normalizedName)->first();

            // If values are empty, delete the attribute entirely.
            if (empty($values)) {
                $attribute?->delete();

                continue;
            }

            // Create or retrieve the attribute.
            if (! $attribute) {
                $attribute = $model->attributes()->create(['name' => $normalizedName]);
            }

            // Delete existing values.
            $attribute->values()->each(
                fn (LdapObjectAttributeValue $value) => $value->delete()
            );

            // Add new values.
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
        // First, check if any attribute contains a valid GUID value.
        foreach ($attributes as $attribute => $values) {
            if ($this->attributeValueIsGuid($values)) {
                return strtolower($attribute);
            }
        }

        // Fall back to checking for well-known GUID attribute keys.
        $knownGuidKeys = ['objectguid', 'entryuuid', 'nsuniqueid', 'ipauniqueid', 'guid'];

        foreach ($knownGuidKeys as $key) {
            if (Arr::has($attributes, $key)) {
                return $key;
            }
        }

        // Default to 'objectguid' as the most common GUID key.
        return 'objectguid';
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
    public function paginate(int $pageSize = 1000, bool $isCritical = false): array
    {
        return $this->get();
    }

    /**
     * {@inheritdoc}
     */
    public function run(string $filter): mixed
    {
        // Reset the Eloquent query to ensure fresh state for each run.
        // This is necessary because the same builder may be used for
        // multiple queries (e.g., findMany calling find() in a loop).
        $this->eloquent = $this->newEloquentQuery();

        // Re-apply the filter that was built during query building.
        if ($this->filter !== null) {
            $this->applyFilterToEloquentQuery($this->filter);
        }

        if ($this->limit > 0) {
            $this->eloquent->limit($this->limit);
        }

        if (! in_array('*', $this->selects ?? ['*'])) {
            $this->only = $this->selects;
        }

        switch ($this->type) {
            case 'read':
                // Emulate performing a single "read" operation.
                $this->eloquent->where('dn', '=', $this->dn);
                break;
            case 'list':
                // Emulate performing a directory "listing" operation.
                $this->eloquent->where('parent_dn', '=', $this->dn);
                break;
            case 'search':
                // Emulate performing a global directory "search" operation.
                if ($this->dn) {
                    $this->eloquent->where('dn', 'like', "%{$this->dn}");
                }
                break;
        }

        return $this->eloquent->get();
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

    /**
     * {@inheritdoc}
     */
    public function add(string $dn, array $attributes): bool
    {
        if (! $model = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        foreach ($attributes as $name => $values) {
            $normalizedName = $this->normalizeAttributeName($name);

            /** @var LdapObjectAttribute $attribute */
            $attribute = $model->attributes()->firstOrCreate([
                'name' => $normalizedName,
            ]);

            foreach ((array) $values as $value) {
                $attribute->values()->create(['value' => $value]);
            }
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function update(string $dn, array $modifications): bool
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
    public function replace(string $dn, array $attributes): bool
    {
        return $this->updateAttributes($dn, $attributes);
    }

    /**
     * {@inheritdoc}
     */
    public function remove(string $dn, array $attributes): bool
    {
        if (! $model = $this->findEloquentModelByDn($dn)) {
            return false;
        }

        foreach ($attributes as $attribute => $values) {
            $normalizedName = $this->normalizeAttributeName($attribute);

            /** @var LdapObjectAttribute|null $attr */
            $attr = $model->attributes()->where('name', $normalizedName)->first();

            if (! $attr) {
                continue;
            }

            if (empty($values)) {
                // Remove the entire attribute.
                $attr->delete();
            } else {
                // Remove specific values.
                foreach ((array) $values as $value) {
                    $attr->values()->where('value', $value)->each(
                        fn (LdapObjectAttributeValue $attrValue) => $attrValue->delete()
                    );
                }
            }
        }

        return true;
    }
}
