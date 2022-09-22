<?php

namespace LdapRecord\Laravel\Import;

use Closure;
use Exception;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use LdapRecord\Laravel\DetectsSoftDeletes;
use LdapRecord\Laravel\Events\Import\Completed;
use LdapRecord\Laravel\Events\Import\DeletedMissing;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\ImportFailed;
use LdapRecord\Laravel\Events\Import\Restored;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Events\Import\Started;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Query\Model\Builder as LdapQuery;

class Importer
{
    use DetectsSoftDeletes;

    /**
     * The LdapRecord model to use for importing.
     *
     * @var string|null
     */
    protected $model;

    /**
     * The Eloquent database model.
     *
     * @var string
     */
    protected $eloquent;

    /**
     * The synchronizer instance.
     *
     * @var Synchronizer|null
     */
    protected $synchronizer;

    /**
     * The LDAP objects to import.
     *
     * @var \LdapRecord\Query\Collection|null
     */
    protected $objects;

    /**
     * The successfully imported LDAP object Eloquent models.
     *
     * @var Collection|null
     */
    protected $imported;

    /**
     * The sync attributes to use for the import.
     *
     * @var array|null
     */
    protected $syncAttributes;

    /**
     * The callback to use for synchronizing attributes.
     *
     * @var Closure|null
     */
    protected $syncUsingCallback;

    /**
     * The attributes to request from the LDAP server.
     *
     * @var string|null
     */
    protected $onlyAttributes;

    /**
     * The filter to use for limiting LDAP query results.
     *
     * @var string|null
     */
    protected $filter;

    /**
     * The query scopes to apply to the query.
     *
     * @var array
     */
    protected $scopes = [];

    /**
     * Whether to trash Eloquent models that were missing from the import.
     *
     * @var bool
     */
    protected $softDeleteMissing = false;

    /**
     * Whether to restore trashed Eloquent models that were previously missing.
     *
     * @var bool
     */
    protected $softRestoreDiscovered = false;

    /**
     * Set the LdapRecord model to use for importing.
     *
     * @param string $model
     *
     * @return $this
     */
    public function setLdapModel($model)
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the Eloquent model to import LDAP objects into.
     *
     * @param string $eloquent
     *
     * @return $this
     */
    public function setEloquentModel($eloquent)
    {
        $this->eloquent = $eloquent;

        return $this;
    }

    /**
     * Get the loaded objects to be imported.
     *
     * @return \LdapRecord\Query\Collection
     */
    public function getLdapObjects()
    {
        return $this->objects;
    }

    /**
     * Set the objects to import.
     *
     * @param \LdapRecord\Query\Collection $objects
     *
     * @return $this
     */
    public function setLdapObjects($objects)
    {
        $this->objects = $objects;

        return $this;
    }

    /**
     * Set the attribute map for synchronizing database attributes.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function setSyncAttributes(array $attributes)
    {
        $this->syncAttributes = $attributes;

        return $this;
    }

    /**
     * Limit the LDAP query to return only the given attributes.
     *
     * @param string|array $attributes
     *
     * @return $this
     */
    public function setLdapRequestAttributes($attributes)
    {
        $this->onlyAttributes = $attributes;

        return $this;
    }

    /**
     * Apply a raw LDAP filter to the import query.
     *
     * @param string $filter
     *
     * @return $this
     */
    public function setLdapRawFilter($filter)
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Apply LDAP scopes to the import query.
     *
     * @param array|string $scopes
     *
     * @return $this
     */
    public function setLdapScopes($scopes = [])
    {
        $this->scopes = Arr::wrap($scopes);

        return $this;
    }

    /**
     * Set the LDAP synchronizer to use.
     *
     * @param Synchronizer $synchronizer
     *
     * @return $this
     */
    public function setLdapSynchronizer(Synchronizer $synchronizer)
    {
        $this->synchronizer = $synchronizer;

        return $this;
    }

    /**
     * Import objects using a callback.
     *
     * @param Closure $callback
     *
     * @return $this
     */
    public function syncAttributesUsing(Closure $callback)
    {
        $this->syncUsingCallback = $callback;

        return $this;
    }

    /**
     * Soft-delete all Eloquent models that were missing from the import.
     *
     * @return $this
     */
    public function enableSoftDeletes()
    {
        $this->softDeleteMissing = true;

        return $this;
    }

    /**
     * Soft-restore all Eloquent models that were previously missing.
     *
     * @return $this
     */
    public function enableSoftRestore()
    {
        $this->softRestoreDiscovered = true;

        return $this;
    }

    /**
     * Execute the import.
     *
     * @return Collection
     *
     * @throws ImportException
     * @throws \LdapRecord\LdapRecordException
     */
    public function execute()
    {
        $synchronizer = $this->getSynchronizer();

        if (! $this->model && ! $this->hasImportableObjects()) {
            throw new ImportException('No LdapRecord model or importable objects have been defined.');
        }

        // We will attempt to retrieve the LDAP model
        // instance to be able to retrieve objects
        // from, if none have been loaded yet.
        $ldapRecord = $this->objects
            ? $this->objects->first()->newInstance()
            : $this->createLdapModel();

        // If no LDAP objects have been preloaded, we
        // will load them here using the LDAP model
        // and apply any query constraints.
        if (! $this->objects) {
            $this->objects = $this->applyLdapQueryConstraints(
                $ldapRecord->newQuery()
            )->paginate();
        }

        event(new Started($this->objects));

        $this->imported = $this->import($synchronizer);

        event(new Completed($this->objects, $this->imported));

        if ($this->softDeleteMissing) {
            $this->softDeleteMissing(
                $ldapRecord,
                $synchronizer->createEloquentModel()
            );
        }

        return $this->imported;
    }

    /**
     * Import the objects into the database.
     *
     * @param Synchronizer $synchronizer
     *
     * @return Collection
     */
    protected function import(Synchronizer $synchronizer)
    {
        return Collection::make($this->objects->all())->map(
            $this->buildImportCallback($synchronizer)
        )->filter();
    }

    /**
     * Build the callback that executes the import process on each LDAP object.
     *
     * @param Synchronizer $synchronizer
     *
     * @return Closure
     */
    protected function buildImportCallback(Synchronizer $synchronizer)
    {
        return function ($object) use ($synchronizer) {
            $eloquent = $synchronizer->createOrFindEloquentModel($object);

            try {
                $synchronizer->synchronize($object, $eloquent);

                if ($this->softRestoreDiscovered) {
                    $this->softRestoreDiscovered($object, $eloquent);
                }

                $eloquent->save();

                event(new Saved($object, $eloquent));

                if ($eloquent->wasRecentlyCreated) {
                    event(new Imported($object, $eloquent));
                }

                return $eloquent;
            } catch (Exception $e) {
                event(new ImportFailed($object, $eloquent, $e));
            }
        };
    }

    /**
     * Get or make the synchronizer.
     *
     * @return Synchronizer
     *
     * @throws ImportException
     */
    protected function getSynchronizer()
    {
        $synchronizer = $this->synchronizer ?? $this->createSynchronizer();

        if ($this->syncUsingCallback) {
            $synchronizer->syncUsing($this->syncUsingCallback);
        }

        return $synchronizer;
    }

    /**
     * Create a new LDAP synchronizer.
     *
     * @return Synchronizer
     *
     * @throws ImportException
     */
    protected function createSynchronizer()
    {
        if (! $this->eloquent) {
            throw new ImportException('No Eloquent model has been defined for importing.');
        }

        if (! $this->syncUsingCallback && empty($this->syncAttributes)) {
            throw new ImportException('Sync attributes or a sync callback must be defined to import objects.');
        }

        return app(Synchronizer::class, [
            'eloquentModel' => $this->eloquent,
            'config' => ['sync_attributes' => $this->syncAttributes],
        ]);
    }

    /**
     * Apply the import constraints to the query.
     *
     * @param LdapQuery $query
     *
     * @return LdapQuery
     */
    protected function applyLdapQueryConstraints(LdapQuery $query)
    {
        if ($this->filter) {
            $query->rawFilter($this->filter);
        }

        if ($this->onlyAttributes) {
            $query->select($this->onlyAttributes);
        }

        foreach ($this->scopes as $scope) {
            $query->withGlobalScope($scope, app($scope));
        }

        return $query;
    }

    /**
     * Soft-restore the discovered LDAP objects Eloquent model.
     *
     * @param LdapRecord $object
     * @param Eloquent   $model
     *
     * @return void
     */
    protected function softRestoreDiscovered(LdapRecord $object, Eloquent $model)
    {
        if (! $this->isUsingSoftDeletes($model)) {
            return;
        }

        if (! $model->trashed()) {
            return;
        }

        $model->restore();

        event(new Restored($object, $model));
    }

    /**
     * Soft-delete missing Eloquent models that are missing from the imported.
     *
     * @param LdapRecord $ldap
     * @param Eloquent   $eloquent
     *
     * @return void
     */
    protected function softDeleteMissing(LdapRecord $ldap, Eloquent $eloquent)
    {
        if (! $this->isUsingSoftDeletes($eloquent)) {
            return;
        }

        if ($this->objects->isEmpty()) {
            return;
        }

        $domain = $ldap->getConnectionName() ?? Config::get('ldap.default');

        $existing = $eloquent->newQuery()
            ->whereNotNull($eloquent->getLdapGuidColumn())
            ->where($eloquent->getLdapDomainColumn(), '=', $domain)
            ->pluck($eloquent->getLdapGuidColumn());

        $toDelete = $existing->diff(
            $this->imported->pluck($eloquent->getLdapGuidColumn())
        );

        if ($toDelete->isEmpty()) {
            return;
        }

        $deleted = $eloquent->newQuery()
            ->whereNotNull($eloquent->getLdapGuidColumn())
            ->where($eloquent->getLdapDomainColumn(), '=', $domain)
            ->whereIn($eloquent->getLdapGuidColumn(), $toDelete->toArray())
            ->update([$eloquent->getDeletedAtColumn() => now()]);

        if ($deleted > 0) {
            event(new DeletedMissing($ldap, $eloquent, $toDelete));
        }
    }

    /**
     * Determine if importable objects have been set.
     *
     * @return bool
     */
    protected function hasImportableObjects()
    {
        return $this->objects && $this->objects->isNotEmpty();
    }

    /**
     * Create a new LdapRecord model.
     *
     * @return LdapRecord
     */
    protected function createLdapModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }
}
