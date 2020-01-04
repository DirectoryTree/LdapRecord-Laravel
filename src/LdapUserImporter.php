<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Models\Model as LdapUser;

class LdapUserImporter
{
    /**
     * The Eloquent model to use for importing.
     *
     * @var string
     */
    protected $eloquentModel;

    /**
     * The import configuration.
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor.
     *
     * @param string $eloquentModel
     * @param array  $config
     */
    public function __construct(string $eloquentModel, array $config)
    {
        $this->eloquentModel = $eloquentModel;
        $this->config = $config;
    }

    /**
     * Import / synchronize the LDAP user.
     *
     * @param LdapUser   $user
     * @param string|null $password
     *
     * @return EloquentModel
     */
    public function run(LdapUser $user, $password = null)
    {
        // Here we'll try to locate the eloquent user model
        // from the LDAP users model. If one cannot be
        // located, we'll create a new instance.
        $model = $this->createOrFindEloquentModel($user);

        if (! $model->exists) {
            event(new Importing($user, $model));
        }

        event(new Synchronizing($user, $model));

        $config = array_merge($this->config, compact('password'));

        $this->hydrate($user, $model, $config);

        event(new Synchronized($user, $model));

        return $model;
    }

    /**
     * Hydrate the eloquent model with the LDAP user.
     *
     * @param LdapUser      $user
     * @param EloquentModel $model
     * @param array         $attributes
     *
     * @return void
     */
    protected function hydrate(LdapUser $user, $model, array $attributes = [])
    {
        LdapUserHydrator::hydrate($user, $model, $attributes);
    }

    /**
     * Retrieves an eloquent user by their GUID or their username.
     *
     * @param LdapUser $user
     *
     * @return EloquentModel|null
     */
    protected function createOrFindEloquentModel(LdapUser $user)
    {
        $model = $this->createEloquentModel();

        $query = $model->newQuery();

        if ($query->getMacro('withTrashed')) {
            // If the withTrashed macro exists on our User model, then we must be
            // using soft deletes. We need to make sure we include these
            // results so we don't create duplicate user records.
            $query->withTrashed();
        }

        return $query
            ->where($model->getLdapGuidColumn(), '=', $user->getConvertedGuid())
            ->first() ?? $model->newInstance();
    }

    /**
     * Get the name of the eloquent model.
     *
     * @return string
     */
    public function getEloquentModel()
    {
        return $this->eloquentModel;
    }

    /**
     * Get a new domain database model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function createEloquentModel()
    {
        $class = '\\'.ltrim($this->eloquentModel, '\\');

        return new $class;
    }
}
