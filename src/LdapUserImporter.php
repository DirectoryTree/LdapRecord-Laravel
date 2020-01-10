<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
     * @param LdapUser    $user
     * @param string|null $password
     *
     * @return EloquentModel
     */
    public function run(LdapUser $user, $password = null)
    {
        $eloquent = $this->createOrFindEloquentModel($user);

        if (! $eloquent->exists) {
            event(new Importing($user, $eloquent));
        }

        event(new Synchronizing($user, $eloquent));

        $this->hydrate($user, $eloquent, $this->config);

        if ($this->hasPasswordColumn()) {
            // We will ensure that we always have a password to
            // save to the user, even if one is not given.
            $password = $password ?? Str::random();

            // If password sync is disabled, we will be sure to overwrite
            // the password so it is not saved to the eloquent model.
            if (! $this->isSyncingPasswords()) {
                $password = Str::random();
            }

            if ($this->passwordNeedsUpdate($eloquent, $password)) {
                $this->setPassword($eloquent, $password);
            }
        }

        event(new Synchronized($user, $eloquent));

        return $eloquent;
    }

    /**
     * Hydrate the eloquent model with the LDAP user.
     *
     * @param LdapUser      $user
     * @param EloquentModel $model
     * @param array         $config
     *
     * @return void
     */
    protected function hydrate(LdapUser $user, $model, array $config = [])
    {
        EloquentUserHydrator::hydrate($user, $model, $config);
    }

    /**
     * Retrieves an eloquent user by their GUID.
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
     * Set the configuration for the importer to use.
     *
     * @param array $config
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
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

    /**
     * Set the password on the users model.
     *
     * @param EloquentModel $model
     * @param string        $password
     *
     * @return void
     */
    protected function setPassword(EloquentModel $model, $password)
    {
        // If the model has a mutator for the password field, we
        // can assume hashing passwords is taken care of.
        // Otherwise, we will hash it normally.
        $password = $model->hasSetMutator($this->passwordColumn()) ? $password : Hash::make($password);

        $model->setAttribute($this->passwordColumn(), $password);
    }

    /**
     * Determine if the current model requires a password update.
     *
     * This checks if the model does not currently have a
     * password, or if the password fails a hash check.
     *
     * @param EloquentModel $model
     * @param string|null   $password
     *
     * @return bool
     */
    protected function passwordNeedsUpdate(EloquentModel $model, $password = null)
    {
        $current = $this->currentModelPassword($model);

        // If the application is running in console, we will assume the
        // import command is being run. In this case, we do not want
        // to overwrite a password that's already properly hashed.
        if (app()->runningInConsole() && ! Hash::needsRehash($current)) {
            return false;
        }

        // If the eloquent model contains a password and password sync is
        // enabled, we will check the integrity of the given password
        // against it to determine if it should be updated.
        if (! is_null($current) && $this->isSyncingPasswords()) {
            return ! Hash::check($password, $current);
        }

        return is_null($current);
    }

    /**
     * Determines if the developer has configured a password column.
     *
     * @return bool
     */
    protected function hasPasswordColumn()
    {
        return $this->passwordColumn() !== false;
    }

    /**
     * Get the current models hashed password.
     *
     * @param EloquentModel $model
     *
     * @return string|null
     */
    protected function currentModelPassword(EloquentModel $model)
    {
        return $model->getAttribute($this->passwordColumn());
    }

    /**
     * Get the configured database password column to use.
     *
     * @return string|false
     */
    protected function passwordColumn()
    {
        return Arr::get($this->config, 'password_column', 'password');
    }

    /**
     * Determine whether password sync is enabled.
     *
     * @return bool
     */
    protected function isSyncingPasswords()
    {
        return Arr::get($this->config, 'sync_passwords', false);
    }
}
