<?php

namespace LdapRecord\Laravel;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Events\Auth\DiscoveredWithCredentials;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;

class LdapUserRepository
{
    /**
     * The name of the LdapRecord user model.
     */
    protected string $model;

    /**
     * The scopes to apply to the model query.
     */
    protected array $scopes = [];

    /**
     * The credential array keys to bypass.
     */
    protected array $bypassCredentialKeys = ['password', 'fallback'];

    /**
     * Constructor.
     *
     * @param  class-string  $model
     */
    public function __construct(string $model, array $scopes = [])
    {
        $this->model = $model;
        $this->scopes = $scopes;
    }

    /**
     * Get a user by the attribute and value.
     */
    public function findBy(string $attribute, mixed $value): ?Model
    {
        return $this->query()->findBy($attribute, $value);
    }

    /**
     * Get an LDAP user by their eloquent model.
     */
    public function findByModel(LdapAuthenticatable $model): ?Model
    {
        if (empty($guid = $model->getLdapGuid())) {
            return null;
        }

        return $this->findByGuid($guid);
    }

    /**
     * Get a user by their object GUID.
     */
    public function findByGuid(string $guid): ?Model
    {
        return $this->query()->findByGuid($guid);
    }

    /**
     * Retrieve a user by the given credentials.
     */
    public function findByCredentials(array $credentials = []): ?Model
    {
        if (empty($credentials)) {
            return null;
        }

        $query = $this->query();

        foreach ($credentials as $key => $value) {
            if (Str::contains($key, $this->bypassCredentialKeys)) {
                continue;
            }

            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        if (is_null($user = $query->first())) {
            return null;
        }

        event(new DiscoveredWithCredentials($user));

        return $user;
    }

    /**
     * Get a new model query.
     */
    public function query(): Builder
    {
        $this->applyScopes(
            $query = $this->newModelQuery()
        );

        return $query;
    }

    /**
     * Set the scopes to apply on the query.
     */
    public function setScopes(array $scopes): static
    {
        $this->scopes = $scopes;

        return $this;
    }

    /**
     * Apply the configured scopes to the query.
     */
    protected function applyScopes(Builder $query): void
    {
        foreach ($this->scopes as $identifier => $scope) {
            match (true) {
                is_string($scope) => $query->withGlobalScope($scope, app($scope)),
                is_callable($scope) => $query->withGlobalScope($identifier, $scope),
                is_object($scope) => $query->withGlobalScope(get_class($scope), $scope),
            };
        }
    }

    /**
     * Create a new instance of the LdapRecord model.
     */
    public function createModel(): Model
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Gets the name of the LdapRecord user model.
     *
     * @return class-string
     */
    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Get a new query builder for the model instance.
     */
    protected function newModelQuery(?Model $model = null): Builder
    {
        $model = is_null($model) ? $this->createModel() : $model;

        $query = $model->newQuery();

        // We will ensure our object GUID attribute is always selected
        // along will all attributes. Otherwise, if the object GUID
        // attribute is virtual, it may not be returned in results.
        $selects = array_merge(['*', $model->getGuidKey()], $query->getSelects());

        return $query->select(array_unique($selects));
    }
}
