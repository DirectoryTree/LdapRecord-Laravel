<?php

namespace LdapRecord\Laravel;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class LdapUserRepository
{
    /**
     * The name of the LdapRecord user model.
     *
     * @var string
     */
    protected $model;

    /**
     * The scopes to apply to the LdapRecord model.
     *
     * @var array
     */
    protected $scopes;

    /**
     * Constructor.
     *
     * @param string $model
     * @param array  $scopes
     */
    public function __construct($model, $scopes = [])
    {
        $this->model = $model;
        $this->scopes = (array) $scopes;
    }

    /**
     * Get a user by the attribute and value.
     *
     * @param string $attribute
     * @param string $value
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function findBy($attribute, $value)
    {
        return $this->query()->findBy($attribute, $value);
    }

    /**
     * Get an LDAP user by their eloquent model.
     *
     * @param LdapAuthenticatable $model
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function findByModel(LdapAuthenticatable $model)
    {
        return $this->findByGuid($model->getLdapGuid());
    }

    /**
     * Get a user by their object GUID.
     *
     * @param string $guid
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function findByGuid($guid)
    {
        if ($user = $this->query()->findByGuid($guid)) {
            return $user;
        }
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param array  $credentials
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function findByCredentials(array $credentials = [])
    {
        if (empty($credentials)) {
            return;
        }

        $query = $this->query();

        foreach ($credentials as $key => $value) {
            if (Str::contains($key, 'password')) {
                continue;
            }

            if (is_array($value) || $value instanceof Arrayable) {
                $query->whereIn($key, $value);
            } else {
                $query->where($key, $value);
            }
        }

        return $query->first();
    }

    /**
     * Get a new model query.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function query()
    {
        return tap($this->newModelQuery(), function ($query) {
            foreach ($this->scopes() as $scope) {
                $scope->apply($query);
            }
        });
    }

    /**
     * Create a new instance of the LdapRecord model.
     *
     * @return \LdapRecord\Models\Model
     */
    public function createModel()
    {
        $class = '\\'.ltrim($this->model, '\\');

        return new $class;
    }

    /**
     * Gets the name of the LdapRecord user model.
     *
     * @return string
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Get a new query builder for the model instance.
     *
     * @param \LdapRecord\Models\Model|null $model
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    protected function newModelQuery($model = null)
    {
        $model = is_null($model) ? $this->createModel() : $model;

        $query = $model->newQuery();

        // We will ensure our object GUID attribute is always selected
        // along will all attributes. Otherwise, if the object GUID
        // attribute is virtual, it may not be returned.
        $selects = array_unique(array_merge(['*', $model->getGuidKey()], $query->getSelects()));

        return $query->select($selects);
    }

    /**
     * Get the scopes to apply to the model query.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function scopes()
    {
        return collect($this->scopes)->map(function ($scope) {
            return app($scope);
        })->values();
    }
}
