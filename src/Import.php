<?php

namespace LdapRecord\Laravel;

use Exception;
use InvalidArgumentException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Query\Model\Builder as LdapQuery;
use Illuminate\Database\Eloquent\Model as Eloquent;

class Import
{
    use DetectsSoftDeletes;

    /**
     * The LdapRecord model to use for importing.
     *
     * @var string|null
     */
    protected $ldap;

    /**
     * The Eloquent database model.
     *
     * @var string
     */
    protected $eloquent;

    /**
     * The custom LDAP importer to use.
     *
     * @var \LdapRecord\Laravel\LdapImporter|null
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
    protected $using;

    /**
     * The sync attributes to use for the import.
     *
     * @var array|null
     */
    protected $sync;

    /**
     * The attributes to request from the LDAP server.
     *
     * @var string|null
     */
    protected $attributes;

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
    protected $trashMissing = false;

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
     * Constructor.
     *
     * @param string|null $ldap
     */
    public function __construct($ldap = null)
    {
        $this->ldap = $ldap;
    }

    /**
     * Set the Eloquent model to import LDAP objects into.
     *
     * @param string $eloquent
     *
     * @return $this
     */
    public function into($eloquent)
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
    public function objects($objects)
    {
        $this->objects = $objects;

        return $this;
    }

    /**
     * Synchronize
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function syncAttributes(array $attributes)
    {
        $this->sync = $attributes;

        return $this;
    }

    /**
     * Import objects using a callback.
     *
     * @param callable $using
     *
     * @return $this
     */
    public function using(callable $using)
    {
        $this->using = $using;

        return $this;
    }

    /**
     * Limit the LDAP query to return only the given attributes.
     *
     * @param string|array $attributes
     *
     * @return $this
     */
    public function limitAttributes($attributes)
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Apply an LDAP filter to the import query.
     *
     * @param string $filter
     *
     * @return $this
     */
    public function applyFilter($filter)
    {
        $this->filter = $filter;

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
    public function trashMissing()
    {
        $this->trashMissing = true;

        return $this;
    }

    /**
     * Set the LDAP importer to use.
     *
     * @param LdapImporter $importer
     *
     * @return $this
     */
    public function setLdapImporter(LdapImporter $importer)
    {
        $this->importer = $importer;

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
     * @throws \LdapRecord\LdapRecordException
     */
    public function execute()
    {
        $this->registerDefaultCallbacks();

        $importer = $this->importer ?? $this->createLdapImporter();

        if (! $this->ldap && ! $this->hasImportableObjects()) {
            throw new LdapImportException('No LdapRecord model or importable objects have been defined.');
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

        if ($this->trashMissing) {
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
     * @param LdapImporter $importer
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
     * @param LdapImporter $importer
     *
     * @return \Closure
     */
    protected function buildImportCallback($importer)
    {
        return function ($object) use ($importer) {
            $db = $importer->createOrFindEloquentModel($object);

            $this->callEventCallbacks('importing', [$db, $object]);

            try {
                if ($this->using) {
                    call_user_func($this->using, $db, $object);
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
     * @return LdapImporter
     */
    protected function createLdapImporter()
    {
        if (! $this->eloquent) {
            throw new LdapImportException('No Eloquent model has been defined for importing.');
        }

        if (! $this->using && empty($this->sync)) {
            throw new LdapImportException('Sync attributes or a using callback must be defined to import objects.');
        }

        return new LdapImporter(
            $this->eloquent, ['sync_attributes' => $this->sync]
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
        if ($this->attributes) {
            $query->select($this->attributes);
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
        $class = '\\'.ltrim($this->ldap, '\\');

        return new $class;
    }
}
