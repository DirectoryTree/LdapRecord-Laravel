<?php

namespace LdapRecord\Laravel\Import;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LdapRecord\Laravel\DetectsSoftDeletes;
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
     * The custom LDAP importer to use.
     *
     * @var Synchronizer|null
     */
    protected $importer;

    /**
     * The defined set of objects to import.
     *
     * @var \LdapRecord\Query\Collection|null
     */
    protected $objects;

    /**
     * The successfully imported Eloquent models.
     *
     * @var \LdapRecord\Query\Collection|null
     */
    protected $imported;

    /**
     * The callback to use for synchronizing attributes.
     *
     * @var callable|null
     */
    protected $importCallback;

    /**
     * The sync attributes to use for the import.
     *
     * @var array|null
     */
    protected $syncAttributes;

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
     * The import events that callbacks can be registered on.
     *
     * @var array
     */
    protected $events = [
        'importing', 'imported',
        'deleting.missing', 'deleted.missing',
        'starting', 'failed', 'completed',
    ];

    /**
     * The registered event callbacks.
     *
     * @var array
     */
    protected $eventCallbacks = [];

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
     * Import objects using a callback.
     *
     * @param callable $callback
     *
     * @return $this
     */
    public function setImportCallback(callable $callback)
    {
        $this->importCallback = $callback;

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
        $this->importer = $importer;

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
     * Register an event callback on the importer.
     *
     * @param string   $event
     * @param callable $callback
     *
     * @return $this
     */
    public function registerEventCallback($event, callable $callback)
    {
        if (! in_array($event, $this->events)) {
            throw new InvalidArgumentException(
                sprintf('Event [%s] is not a valid import event. Valid events are: %s', $event, implode(', ', $this->events))
            );
        }

        $this->eventCallbacks[$event][] = $callback;

        return $this;
    }

    /**
     * Register the default import event callbacks.
     *
     * @return void
     */
    protected function registerDefaultCallbacks()
    {
        if (! $this->logging) {
            return;
        }

        $this->registerEventCallback('imported', function ($db, $object) {
            if ($db->wasRecentlyCreated) {
                Log::info("Imported user [{$object->getRdn()}]");
            }
        });

        $this->registerEventCallback('failed', function ($db, $object, $e) {
            Log::error("Importing user [{$object->getRdn()}] failed. {$e->getMessage()}");
        });
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
        $this->registerDefaultCallbacks();

        $importer = $this->importer ?? $this->createLdapImporter();

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

        $this->callEventCallbacks('starting', $this->objects);

        $this->imported = $this->import($importer);

        $this->callEventCallbacks('completed', [$this->objects, $this->imported]);

        if ($this->softDeleteMissing) {
            $this->softDeleteMissing(
                $importer->createEloquentModel(),
                $ldapRecord
            );
        }

        $this->flushEventCallbacks();

        return $this->imported;
    }

    /**
     * Import the objects into the database.
     *
     * @param Synchronizer $importer
     *
     * @return \LdapRecord\Query\Collection
     */
    protected function import($importer)
    {
        return $this->objects->map(
            $this->buildImportCallback($importer)
        )->filter();
    }

    /**
     * Build the import callback.
     *
     * @param Synchronizer $importer
     *
     * @return \Closure
     */
    protected function buildImportCallback($importer)
    {
        return function ($object) use ($importer) {
            $db = $importer->createOrFindEloquentModel($object);

            $this->callEventCallbacks('importing', [$db, $object]);

            try {
                if ($this->importCallback) {
                    call_user_func($this->importCallback, $db, $object);
                } else {
                    tap($importer->synchronize($object, $db))->save();
                }

                $this->callEventCallbacks('imported', [$db, $object]);

                return $db;
            } catch (Exception $e) {
                $this->callEventCallbacks('failed', [$db, $object, $e]);
            }
        };
    }

    /**
     * Call and then flush all of the callbacks for the event.
     *
     * @param string $event
     * @param mixed  $arguments
     *
     * @return void
     */
    protected function callEventCallbacks($event, $arguments = [])
    {
        foreach ($this->eventCallbacks[$event] ?? [] as $callback) {
            $callback(...Arr::wrap($arguments));
        }
    }

    /**
     * Flush all the event callbacks.
     *
     * @return void
     */
    protected function flushEventCallbacks()
    {
        $this->eventCallbacks = [];
    }

    /**
     * Create a new LDAP importer.
     *
     * @return Synchronizer
     *
     * @throws ImportException
     */
    protected function createLdapImporter()
    {
        if (! $this->eloquent) {
            throw new ImportException('No Eloquent model has been defined for importing.');
        }

        if (! $this->importCallback && empty($this->syncAttributes)) {
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
     * @param Eloquent                     $db
     * @param LdapRecord                   $ldap
     *
     * @return void
     */
    protected function softDeleteMissing(Eloquent $db, LdapRecord $ldap)
    {
        if (! $this->isUsingSoftDeletes($db)) {
            return;
        }

        if ($this->objects->isEmpty()) {
            return;
        }

        $this->callEventCallbacks('deleting.missing', [$db, $ldap, $this->imported]);

        $domain = $ldap->getConnectionName() ?? config('ldap.default');

        $guids = $this->imported->pluck($db->getLdapGuidColumn())->toArray();

        // Here we'll soft-delete all users whom have a 'guid' present
        // but are missing from our imported guid array and are from
        // our LDAP domain that has just been imported. This ensures
        // the deleted users are the ones from the same domain.
        $deleted = $db->newQuery()
            ->whereNotNull($db->getLdapGuidColumn())
            ->where($db->getLdapDomainColumn(), '=', $domain)
            ->whereNotIn($db->getLdapGuidColumn(), $guids)
            ->update([$db->getDeletedAtColumn() => $deletedAt = now()]);

        if (! $deleted) {
            $this->callEventCallbacks(
                'deleted.missing', [$db, $ldap, $db->newCollection()]
            );

            return;
        }

        // Next, we will retrieve the ID's of all users who
        // were deleted from the above query so we can
        // log them appropriately using an event.
        $ids = $db->newQuery()
            ->onlyTrashed()
            ->select($db->getKeyName())
            ->whereNotNull($db->getLdapGuidColumn())
            ->where($db->getLdapDomainColumn(), '=', $domain)
            ->where($db->getDeletedAtColumn(), '=', $deletedAt)
            ->pluck($db->getKeyName());

        $this->callEventCallbacks(
            'deleted.missing', [$db, $ldap, $ids]
        );
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
