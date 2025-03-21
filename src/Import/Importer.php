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
use LdapRecord\Query\Collection as LdapRecordCollection;
use LdapRecord\Query\Model\Builder as LdapQuery;

class Importer
{
    use DetectsSoftDeletes;

    /**
     * The LdapRecord model to use for importing.
     */
    protected ?string $model = null;

    /**
     * The Eloquent database model.
     */
    protected ?string $eloquent = null;

    /**
     * The synchronizer instance.
     */
    protected ?Synchronizer $synchronizer = null;

    /**
     * The LDAP objects to import.
     */
    protected ?LdapRecordCollection $objects = null;

    /**
     * The successfully imported LDAP object Eloquent models.
     */
    protected ?Collection $imported = null;

    /**
     * The sync attributes to use for the import.
     */
    protected array $syncAttributes = [];

    /**
     * The callback to use for synchronizing attributes.
     */
    protected ?Closure $syncUsingCallback = null;

    /**
     * The attributes to request from the LDAP server.
     */
    protected array $onlyAttributes = [];

    /**
     * The filter to use for limiting LDAP query results.
     */
    protected ?string $filter = null;

    /**
     * The query scopes to apply to the query.
     */
    protected array $scopes = [];

    /**
     * Whether to trash Eloquent models that were missing from the import.
     */
    protected bool $softDeleteMissing = false;

    /**
     * Whether to restore trashed Eloquent models that were previously missing.
     */
    protected bool $softRestoreDiscovered = false;

    /**
     * Set the LdapRecord model to use for importing.
     */
    public function setLdapModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    /**
     * Set the Eloquent model to import LDAP objects into.
     */
    public function setEloquentModel(string $eloquent): static
    {
        $this->eloquent = $eloquent;

        return $this;
    }

    /**
     * Get the loaded objects to be imported.
     */
    public function getLdapObjects(): ?LdapRecordCollection
    {
        return $this->objects;
    }

    /**
     * Set the objects to import.
     */
    public function setLdapObjects(LdapRecordCollection $objects): static
    {
        $this->objects = $objects;

        return $this;
    }

    /**
     * Set the attribute map for synchronizing database attributes.
     */
    public function setSyncAttributes(array $attributes): static
    {
        $this->syncAttributes = $attributes;

        return $this;
    }

    /**
     * Limit the LDAP query to return only the given attributes.
     */
    public function setLdapRequestAttributes(array|string $attributes): static
    {
        $this->onlyAttributes = $attributes;

        return $this;
    }

    /**
     * Apply a raw LDAP filter to the import query.
     */
    public function setLdapRawFilter(string $filter): static
    {
        $this->filter = $filter;

        return $this;
    }

    /**
     * Apply LDAP scopes to the import query.
     *
     * @param  class-string|class-string[]  $scopes
     */
    public function setLdapScopes(array|string $scopes = []): static
    {
        $this->scopes = Arr::wrap($scopes);

        return $this;
    }

    /**
     * Set the LDAP synchronizer to use.
     */
    public function setLdapSynchronizer(Synchronizer $synchronizer): static
    {
        $this->synchronizer = $synchronizer;

        return $this;
    }

    /**
     * Import objects using a callback.
     */
    public function syncAttributesUsing(Closure $callback): static
    {
        $this->syncUsingCallback = $callback;

        return $this;
    }

    /**
     * Soft-delete all Eloquent models that were missing from the import.
     */
    public function enableSoftDeletes(): static
    {
        $this->softDeleteMissing = true;

        return $this;
    }

    /**
     * Soft-restore all Eloquent models that were previously missing.
     */
    public function enableSoftRestore(): static
    {
        $this->softRestoreDiscovered = true;

        return $this;
    }

    /**
     * Execute the import.
     *
     * @throws ImportException
     * @throws \LdapRecord\LdapRecordException
     */
    public function execute(): ?Collection
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
     */
    protected function import(Synchronizer $synchronizer): Collection
    {
        return Collection::make($this->objects->all())->map(
            $this->buildImportCallback($synchronizer)
        )->filter();
    }

    /**
     * Build the callback that executes the import process on each LDAP object.
     */
    protected function buildImportCallback(Synchronizer $synchronizer): Closure
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
     * @throws ImportException
     */
    protected function getSynchronizer(): Synchronizer
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
     * @throws ImportException
     */
    protected function createSynchronizer(): Synchronizer
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
     */
    protected function applyLdapQueryConstraints(LdapQuery $query): LdapQuery
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
     */
    protected function softRestoreDiscovered(LdapRecord $object, Eloquent $model): void
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
     */
    protected function softDeleteMissing(LdapRecord $ldap, Eloquent $eloquent): void
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
            ->where($eloquent->getLdapDomainColumn(), $domain)
            ->whereIn($eloquent->getLdapGuidColumn(), $toDelete->all())
            ->touch($eloquent->getDeletedAtColumn());

        if ($deleted > 0) {
            event(new DeletedMissing($ldap, $eloquent, $toDelete));
        }
    }

    /**
     * Determine if importable objects have been set.
     */
    protected function hasImportableObjects(): bool
    {
        return $this->objects && $this->objects->isNotEmpty();
    }

    /**
     * Create a new LdapRecord model.
     */
    protected function createLdapModel(): LdapRecord
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }
}
