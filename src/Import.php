<?php

namespace LdapRecord\Laravel;

use Exception;
use InvalidArgumentException;
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
        'restoring', 'restored',
        'deleting', 'deleted',
        'deleting.missing', 'deleted.missing',
        'failed', 'completed',
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

        $this->registerDefaultCallbacks();
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
        $this->registerEventCallback('imported', function ($database, $object) {
            if (! $database->wasRecentlyCreated) {
                return;
            }

            if ($this->logging) {
                logger()->info("Imported user [{$object->getRdn()}]");
            }
        });

        $this->registerEventCallback('failed', function ($database, $object, $e) {
            logger()->error("Importing user [{$object->getRdn()}] failed. {$e->getMessage()}");
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
        $importer = $this->importer ?? $this->createLdapImporter();

        if (! $this->ldap && ! $this->objects) {
            throw new LdapImportException('No LdapRecord model or objects have been defined for importing.');
        }

        if ($this->objects) {
            $ldapRecord = $this->objects->first()->newInstance();

            $objects = $this->objects;
        } else {
            $ldapRecord = $this->createLdapModel();

            $objects = $this->applyLdapQueryConstraints(
                $ldapRecord->newQuery()
            )->paginate();
        }

        $imported = $this->import($objects, $importer);

        $this->callEventCallbacks('completed', $imported);

        if ($this->trashMissing) {
            $this->softDeleteMissing(
                $importer->createEloquentModel(),
                $ldapRecord,
                $imported
            );
        }

        return $imported;
    }

    /**
     * Import the objects into the database.
     *
     * @param \LdapRecord\Query\Collection $objects
     * @param LdapImporter                 $importer
     *
     * @return \LdapRecord\Query\Collection
     */
    protected function import($objects, $importer)
    {
        return $objects->map(
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
            $database = $importer->createOrFindEloquentModel($object);

            $this->callEventCallbacks('importing', $database, $object);

            try {
                if ($this->using) {
                    call_user_func($this->using, $database, $object);
                } else {
                    tap($importer->synchronize($object, $database))->save();
                }

                $this->callEventCallbacks('imported', $database, $object);

                return $database;
            } catch (Exception $e) {
                $this->callEventCallbacks('failed', $database, $object, $e);
            }
        };
    }

    /**
     * Call all of the callbacks for the event.
     *
     * @param string   $event
     * @param mixed ...$args
     *
     * @return void
     */
    protected function callEventCallbacks($event, ...$args)
    {
        foreach ($this->eventCallbacks[$event] ?? [] as $callback) {
            $callback(...$args);
        }
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
     * Create a new LdapRecord model.
     *
     * @return LdapRecord
     */
    protected function createLdapModel()
    {
        $class = '\\'.ltrim($this->ldap, '\\');

        return new $class;
    }

    /**
     * Soft-delete missing Eloquent models that are missing from the imported.
     *
     * @param Eloquent                     $database
     * @param LdapRecord                   $ldap
     * @param \LdapRecord\Query\Collection $imported
     *
     * @return void
     */
    protected function softDeleteMissing(Eloquent $database, LdapRecord $ldap, $imported)
    {
        if (! $this->isUsingSoftDeletes($database)) {
            return;
        }

        if ($imported->isEmpty()) {
            return;
        }

        $this->callEventCallbacks('deleting.missing', $database, $ldap, $imported);

        $domain = $ldap->getConnectionName() ?? config('ldap.default');

        $guids = $imported->pluck($database->getLdapGuidColumn())->toArray();

        // Here we'll soft-delete all users whom have a 'guid' present
        // but are missing from our imported guid array and are from
        // our LDAP domain that has just been imported. This ensures
        // the deleted users are the ones from the same domain.
        $deleted = $database->newQuery()
            ->whereNotNull($database->getLdapGuidColumn())
            ->where($database->getLdapDomainColumn(), '=', $domain)
            ->whereNotIn($database->getLdapGuidColumn(), $guids)
            ->update([$database->getDeletedAtColumn() => $deletedAt = now()]);

        if (! $deleted) {
            return;
        }

        // Next, we will retrieve the ID's of all users who
        // were deleted from the above query so we can
        // log them appropriately using an event.
        $ids = $database->newQuery()
            ->onlyTrashed()
            ->select($database->getKeyName())
            ->whereNotNull($database->getLdapGuidColumn())
            ->where($database->getLdapDomainColumn(), '=', $domain)
            ->where($database->getDeletedAtColumn(), '=', $deletedAt)
            ->pluck($database->getKeyName());

        $this->callEventCallbacks('deleted.missing', $database, $ldap, $ids);
    }
}
