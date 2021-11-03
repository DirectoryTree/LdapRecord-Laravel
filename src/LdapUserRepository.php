<?php

namespace LdapRecord\Laravel;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Str;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Events\Auth\DiscoveredWithCredentials;

class LdapUserRepository
{
    /**
     * The name of the LdapRecord user model.
     *
     * @var string
     */
    protected $model;

    /**
     * The credential array keys to bypass.
     *
     * @var array
     */
    protected $bypassCredentialKeys = ['password', 'fallback'];

    /**
     * Constructor.
     *
     * @param string $model
     */
    public function __construct($model)
    {
        $this->model = $model;
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
        return $this->query()->findByGuid($guid);
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param array $credentials
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
            return;
        }

        event(new DiscoveredWithCredentials($user));

        return $user;
    }

    /**
     * Get a new model query.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function query()
    {
        return $this->newModelQuery();
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
        // attribute is virtual, it may not be returned in results.
        $selects = array_merge(['*', $model->getGuidKey()], $query->getSelects());

        return $query->select(array_unique($selects));
    }
}
