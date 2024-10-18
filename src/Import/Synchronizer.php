<?php

namespace LdapRecord\Laravel\Import;

use Closure;
use Illuminate\Database\Eloquent\Builder;
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
     */
    protected string $eloquentModel;

    /**
     * The import configuration.
     */
    protected array $config;

    /**
     * The callback to use for synchronization.
     */
    protected ?Closure $syncUsingCallback = null;

    /**
     * Constructor.
     */
    public function __construct(string $eloquentModel, array $config)
    {
        $this->eloquentModel = $eloquentModel;
        $this->config = $config;
    }

    /**
     * Set a callback to use for syncing attributes.
     */
    public function syncUsing(Closure $callback): static
    {
        $this->syncUsingCallback = $callback;

        return $this;
    }

    /**
     * Import/synchronize the LDAP object with its database model.
     */
    public function run(LdapModel $object, array $data = []): EloquentModel
    {
        return $this->synchronize(
            $object,
            $this->createOrFindEloquentModel($object),
            $data
        );
    }

    /**
     * Synchronize the Eloquent database model with the LDAP model.
     */
    public function synchronize(LdapModel $object, EloquentModel $eloquent, array $data = []): EloquentModel
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
     * @throws LdapRecordException
     */
    public function createOrFindEloquentModel(LdapModel $ldap): EloquentModel
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
     */
    protected function hydrate(LdapModel $ldap, EloquentModel $model, array $data = []): void
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
     * @return class-string
     */
    protected function hydrator(): string
    {
        return EloquentHydrator::class;
    }

    /**
     * Applies the configured 'sync existing' scopes to the database query.
     */
    protected function applySyncScopesToQuery(LdapModel $ldap, Builder $query): Builder
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
     */
    protected function getSyncScopeOperatorAndValue(LdapModel $ldap, array|string $config): array
    {
        $attribute = $this->getSyncScopeOption($config, 'attribute', $config);

        $operator = $this->getSyncScopeOption($config, 'operator', '=');

        $value = $ldap->getFirstAttribute($attribute) ?? $attribute;

        return [$operator, $value];
    }

    /**
     * Get the sync scope option from the config array.
     */
    protected function getSyncScopeOption(array|string $config, string $option, array|string|null $default = null): array|string
    {
        return is_array($config) ? $config[$option] : $default;
    }

    /**
     * Creates a new query on the given model.
     */
    protected function newEloquentQuery(EloquentModel $model): Builder
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
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    /**
     * Get the importer configuration.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Get the name of the eloquent model.
     */
    public function getEloquentModel(): string
    {
        return $this->eloquentModel;
    }

    /**
     * Get a new domain database model.
     */
    public function createEloquentModel(): EloquentModel
    {
        $class = '\\'.ltrim($this->eloquentModel, '\\');

        return new $class;
    }
}
