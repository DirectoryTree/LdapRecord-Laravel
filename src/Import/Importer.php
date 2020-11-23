<?php

namespace LdapRecord\Laravel\Import;

use Closure;
use Exception;
use LdapRecord\Laravel\DetectsSoftDeletes;
use LdapRecord\Laravel\Events\Import\BulkImportCompleted;
use LdapRecord\Laravel\Events\Import\BulkImportDeletedMissing;
use LdapRecord\Laravel\Events\Import\BulkImportStarted;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\ImportFailed;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Query\Model\Builder as LdapQuery;
use Illuminate\Database\Eloquent\Model as Eloquent;

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
     * The defined set of objects to import.
     *
     * @var \LdapRecord\Query\Collection|null
     */
    protected $objects;

    /**
     * The successfully imported Eloquent models.
     *
     * @var \Illuminate\Support\Collection|null
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
     * Whether logging is enabled.
     *
     * @var bool
     */
    protected $logging = true;

    /**
     * Whether to trash Eloquent models that were missing from the import.
     *
     * @var bool
     */
    protected $softDeleteMissing = false;

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
    public function setLdapSyncAttributes(array $attributes)
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
     * Set the LDAP importer to use.
     *
     * @param Synchronizer $importer
     *
     * @return $this
     */
    public function setLdapImporter(Synchronizer $importer)
    {
        $this->synchronizer = $importer;

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
     * Disable writing to the log when objects are imported.
     *
     * @return $this
     */
    public function disableLogging()
    {
        $this->logging = false;

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
     * Execute the import.
     *
     * @return \LdapRecord\Query\Collection
     *
     * @throws ImportException
     * @throws \LdapRecord\LdapRecordException
     */
    public function execute()
    {
        $importer = $this->getSynchronizer();

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

        event(new BulkImportStarted($this->objects));

        $this->imported = $this->import($importer);

        event(new BulkImportCompleted($this->objects, $this->imported));

        if ($this->softDeleteMissing) {
            $this->softDeleteMissing(
                $importer->createEloquentModel(),
                $ldapRecord
            );
        }

        return $this->imported;
    }

    /**
     * Import the objects into the database.
     *
     * @param Synchronizer $importer
     *
     * @return \Illuminate\Support\Collection
     */
    protected function import($importer)
    {
        return collect($this->objects->all())->map(
            $this->buildImportCallback($importer)
        )->filter();
    }

    /**
     * Build the import callback.
     *
     * @param Synchronizer $importer
     *
     * @return Closure
     */
    protected function buildImportCallback($importer)
    {
        return function ($object) use ($importer) {
            $eloquent = $importer->createOrFindEloquentModel($object);

            try {
                $importer->synchronize($object, $eloquent)->save();

                event(new Imported($object, $eloquent));

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
            throw new ImportException('Sync attributes or a using callback must be defined to import objects.');
        }

        return new Synchronizer(
            $this->eloquent, ['sync_attributes' => $this->syncAttributes]
        );
    }

    /**
     * Apply the imports LDAP query constraints.
     *
     * @param LdapQuery $query
     *
     * @return LdapQuery
     */
    protected function applyLdapQueryConstraints(LdapQuery $query)
    {
        if ($this->onlyAttributes) {
            $query->select($this->onlyAttributes);
        }

        if ($this->filter) {
            $query->rawFilter($this->filter);
        }

        return $query;
    }

    /**
     * Soft-delete missing Eloquent models that are missing from the imported.
     *
     * @param Eloquent                     $eloquent
     * @param LdapRecord                   $ldap
     *
     * @return void
     */
    protected function softDeleteMissing(Eloquent $eloquent, LdapRecord $ldap)
    {
        if (! $this->isUsingSoftDeletes($eloquent)) {
            return;
        }

        if ($this->objects->isEmpty()) {
            return;
        }

        $domain = $ldap->getConnectionName() ?? config('ldap.default');

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
            ->update([$eloquent->getDeletedAtColumn() => $deletedAt = now()]);

        if ($deleted > 0) {
            event(new BulkImportDeletedMissing($ldap, $eloquent, $toDelete));
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
