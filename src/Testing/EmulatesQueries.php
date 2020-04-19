<?php

namespace LdapRecord\Laravel\Testing;

use Closure;
use LdapRecord\Connection;
use LdapRecord\Models\BatchModification;
use LdapRecord\Query\Model\Builder;

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
     * @return EmulatedModelBuilder|Builder
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
     * {@inheritdoc}
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
     * {@inheritdoc}
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
        return $this->query->where('dn', '=', $dn)->first();
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
        return $this->query->where('guid', '=', $guid)->first();
    }

    /**
     * {@inheritdoc}
     */
    public function addFilter($type, array $bindings)
    {
        $relationMethod = $this->determineRelationMethod($type, $bindings);

        // If the relation method is "not has", we will flip it to
        // a "has" filter and change the relation method so
        // database results are retrieved properly.
        if (in_array($relationMethod, ['whereDoesntHave', 'orWhereDoesntHave'])) {
            $bindings['operator'] = '*';
        }

        $this->query->{$relationMethod}('attributes', function ($query) use ($type, $bindings) {
            $this->addFilterToDatabaseQuery(
                $query,
                $bindings['field'],
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
            $this->nested &&
            $this->nestedState = 'or' &&
            $this->fieldIsUsedMultipleTimes($type, $bindings['field'])
        ) {
            $method = $method == 'whereDoesntHave' ?
                'orWhereDoesntHave' :
                'orWhereHas';
        }

        return $method;
    }

    /**
     * Adds an LDAP "Where" filter to the underlying Eloquent builder.
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

        $attribute = $model->attributes()->firstOrCreate(['name' => $name]);

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
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    public function delete($dn)
    {
        $database = $this->findEloquentModelByDn($dn);

        return $database ? $database->delete() : false;
    }

    /**
     * {@inheritdoc}
     */
    public function escape($value, $ignore = '', $flags = 0)
    {
        return new UnescapedValue($value);
    }

    /**
     * {@inheritdoc}
     */
    public function paginate($pageSize = 1000, $isCritical = false)
    {
        return $this->get();
    }

    /**
     * {@inheritdoc}
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
     * {@inheritdoc}
     */
    protected function parse($resource)
    {
        return $resource->toArray();
    }
}
