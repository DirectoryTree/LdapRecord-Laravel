<?php

namespace LdapRecord\Laravel\Import;

use Closure;
use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model as LdapModel;

class Synchronizer
{
    /**
     * The Eloquent model to use for importing.
     *
     * @var string
     */
    protected $eloquentModel;

    /**
     * The import configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * @var callable
     */
    protected $syncUsingCallback;

    /**
     * Constructor.
     *
     * @param string $eloquentModel
     * @param array  $config
     */
    public function __construct($eloquentModel, array $config)
    {
        $this->eloquentModel = $eloquentModel;
        $this->config = $config;
    }

    /**
     * Set a callback to use for syncing attributes.
     *
     * @param Closure $callback
     *
     * @return $this
     */
    public function syncUsing(Closure $callback)
    {
        $this->syncUsingCallback = $callback;

        return $this;
    }

    /**
     * Import / synchronize the LDAP object with its database model.
     *
     * @param LdapModel $object
     * @param array     $data
     *
     * @return EloquentModel
     */
    public function run(LdapModel $object, array $data = [])
    {
        return $this->synchronize(
            $object,
            $this->createOrFindEloquentModel($object),
            $data
        );
    }

    /**
     * Synchronize the Eloquent database model with the LDAP model.
     *
     * @param LdapModel     $object
     * @param EloquentModel $eloquent
     * @param array         $data
     *
     * @return EloquentModel
     */
    public function synchronize(LdapModel $object, EloquentModel $eloquent, array $data = [])
    {
        if (! $eloquent->exists) {
            event(new Importing($object, $eloquent));
        }

        event(new Synchronizing($object, $eloquent));

        $this->syncUsingCallback
            ? call_user_func($this->syncUsingCallback, $object, $eloquent)
            : $this->hydrate($object, $eloquent, $data);

        event(new Synchronized($object, $eloquent));

        return $eloquent;
    }

    /**
     * Retrieves an eloquent model by their GUID.
     *
     * @param LdapModel $ldap
     *
     * @return EloquentModel
     *
     * @throws LdapRecordException
     */
    public function createOrFindEloquentModel(LdapModel $ldap)
    {
        // We cannot import an LDAP object without a valid GUID
        // identifier. Doing so would cause overwrites of the
        // first database model that does not contain one.
        if (! $guid = $ldap->getConvertedGuid()) {
            throw ImportException::missingGuid($ldap);
        }

        $query = $this->newEloquentQuery(
            $model = $this->createEloquentModel()
        )->where($model->getLdapGuidColumn(), '=', $guid);

        $this->applySyncScopesToQuery($ldap, $query);

        return $query->first() ?? tap($model)->setAttribute($model->getLdapGuidColumn(), $guid);
    }

    /**
     * Hydrate the eloquent model with the LDAP object.
     *
     * @param LdapModel     $ldap
     * @param EloquentModel $model
     * @param array         $data
     *
     * @return void
     */
    protected function hydrate(LdapModel $ldap, $model, array $data = [])
    {
        /** @var EloquentHydrator $hydrator */
        $hydrator = transform($this->hydrator(), function ($hydrator) {
            return new $hydrator($this->config);
        });

        $hydrator->with($data)->hydrate($ldap, $model);
    }

    /**
     * Get the class name of the hydrator to use.
     *
     * @return string
     */
    protected function hydrator()
    {
        return EloquentHydrator::class;
    }

    /**
     * Applies the configured 'sync existing' scopes to the database query.
     *
     * @param LdapModel                             $ldap
     * @param \Illuminate\Database\Eloquent\Builder $query
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applySyncScopesToQuery(LdapModel $ldap, $query)
    {
        $scopes = Arr::get($this->config, 'sync_existing', false);

        if (! is_array($scopes)) {
            return $query;
        }

        return $query->orWhere(function ($query) use ($scopes, $ldap) {
            foreach ($scopes as $databaseColumn => $ldapAttribute) {
                [$operator, $value] = $this->getSyncScopeOperatorAndValue(
                    $ldap,
                    $ldapAttribute
                );

                $query->where($databaseColumn, $operator, $value);
            }
        });
    }

    /**
     * Get the scope clause operator and value from the attribute configuration.
     *
     * @param LdapModel    $ldap
     * @param array|string $config
     *
     * @return array
     */
    protected function getSyncScopeOperatorAndValue(LdapModel $ldap, $config)
    {
        $attribute = $this->getSyncScopeOption($config, 'attribute', $config);

        $operator = $this->getSyncScopeOption($config, 'operator', '=');

        $value = $ldap->getFirstAttribute($attribute) ?? $attribute;

        return [$operator, $value];
    }

    /**
     * Get the sync scope option from the config array.
     *
     * @param array|string $config
     * @param string       $option
     * @param mixed        $default
     *
     * @return mixed
     */
    protected function getSyncScopeOption($config, $option, $default)
    {
        return is_array($config) ? $config[$option] : $default;
    }

    /**
     * Creates a new query on the given model.
     *
     * @param EloquentModel $model
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function newEloquentQuery(EloquentModel $model)
    {
        $query = $model->newQuery();

        if ($query->getMacro('withTrashed')) {
            // If the withTrashed macro exists on our eloquent model, then we must
            // be using soft deletes. We need to make sure we include these
            // results so we don't create duplicate database records.
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Set the configuration for the importer to use.
     *
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    /**
     * Get the importer configuration.
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the name of the eloquent model.
     *
     * @return string
     */
    public function getEloquentModel()
    {
        return $this->eloquentModel;
    }

    /**
     * Get a new domain database model.
     *
     * @return EloquentModel
     */
    public function createEloquentModel()
    {
        $class = '\\'.ltrim($this->eloquentModel, '\\');

        return new $class;
    }
}
