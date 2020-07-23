<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model as LdapModel;

class LdapImporter
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
            $object, $this->createOrFindEloquentModel($object), $data
        );
    }

    /**
     * Synchronize the Eloquent database model with the LDAP model.
     *
     * @param LdapModel     $object
     * @param EloquentModel $database
     * @param array         $data
     *
     * @return EloquentModel
     */
    public function synchronize(LdapModel $object, EloquentModel $database, array $data = [])
    {
        if (! $database->exists) {
            event(new Importing($object, $database));
        }

        event(new Synchronizing($object, $database));

        $this->hydrate($object, $database, $data);

        event(new Synchronized($object, $database));

        return $database;
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
        if (is_null($guid = $ldap->getConvertedGuid())) {
            throw LdapImportException::missingGuid($ldap);
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
                $query->where($databaseColumn, '=', $ldap->getFirstAttribute($ldapAttribute) ?? $ldapAttribute);
            }
        });
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
