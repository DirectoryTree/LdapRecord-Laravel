<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Models\Model as LdapModel;

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
     * The attributes to synchronize.
     *
     * @var array
     */
    protected $attributes = [];

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
     * @param LdapModel   $user
     * @param string|null $password
     *
     * @return Model
     */
    public function run(LdapModel $user, $password = null)
    {
        // Here we'll try to locate the eloquent user model
        // from the LDAP users model. If one cannot be
        // located, we'll create a new instance.
        /** @var LdapAuthenticatable|Model $model */
        $model = $this->createOrFindEloquentModel($user);

        if (! $model->exists) {
            event(new Importing($user, $model));
        }

        event(new Synchronizing($user, $model));

        $this->syncAttributes($user, $model);

        event(new Synchronized($user, $model));

        return $model;
    }

    protected function syncAttributes(LdapModel $user, $model)
    {
        // Set the users LDAP domain and object GUID.
        $model->setLdapGuid($user->getConvertedGuid());
        $model->setLdapDomain($user->getConnectionName());

        foreach ($this->getSyncAttributes() as $modelField => $ldapField) {
            // If the field is a loaded class and contains a `handle()`
            // method, we need to construct the attribute handler.
            if ($this->isAttributeHandler($ldapField)) {
                // We will construct the attribute handler using Laravel's
                // IoC to allow developers to utilize application
                // dependencies in the constructor.
                app($ldapField)->handle($user, $model);
            } else {
                // We'll try to retrieve the value from the LDAP model. If the LDAP field is
                // a string, we'll assume the developer wants the attribute, or a null
                // value. Otherwise, the raw value of the LDAP field will be used.
                $model->{$modelField} = is_string($ldapField) ? $user->getFirstAttribute($ldapField) : $ldapField;
            }
        }
    }

    protected function syncPassword()
    {
        if ($this->hasPasswordColumn()) {
            $password = $this->domain->isSyncingPasswords() ?
                $password : Str::random();

            if ($this->passwordNeedsUpdate($model, $password)) {
                $this->setPassword($model, $password);
            }
        }

        return $model;
    }

    /**
     * Retrieves an eloquent user by their GUID or their username.
     *
     * @param LdapModel $user
     *
     * @return Model|null
     */
    protected function createOrFindEloquentModel(LdapModel $user)
    {
        $model = $this->getNewEloquentModel();

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
    public function getNewEloquentModel()
    {
        return new $this->eloquentModel;
    }

    /**
     * Get the database sync attributes.
     *
     * @return array
     */
    protected function getSyncAttributes()
    {
        return Arr::get($this->config, 'sync_attributes', ['name' => 'cn']);
    }

    /**
     * Set the password on the users model.
     *
     * @param Model  $model
     * @param string $password
     *
     * @return void
     */
    protected function setPassword(Model $model, $password)
    {
        // If the model has a mutator for the password field, we
        // can assume hashing passwords is taken care of.
        // Otherwise, we will hash it normally.
        $password = $model->hasSetMutator($this->column()) ? $password : Hash::make($password);

        $model->setAttribute($this->column(), $password);
    }

    /**
     * Determine if the current model requires a password update.
     *
     * This checks if the model does not currently have a
     * password, or if the password fails a hash check.
     *
     * @param Model       $model
     * @param string|null $password
     *
     * @return bool
     */
    protected function passwordNeedsUpdate(Model $model, $password = null): bool
    {
        $current = $this->currentModelPassword($model);

        if ($current !== null && $this->canSync()) {
            return ! Hash::check($password, $current);
        }

        return is_null($current);
    }

    /**
     * Determines if the developer has configured a password column.
     *
     * @return bool
     */
    protected function hasPasswordColumn(): bool
    {
        return ! is_null($this->column());
    }

    /**
     * Get the current models hashed password.
     *
     * @param Model $model
     *
     * @return string|null
     */
    protected function currentModelPassword(Model $model)
    {
        return $model->getAttribute($this->column());
    }

    /**
     * Get the configured database password column to use.
     *
     * @return string|null
     */
    protected function column()
    {
        return $this->domain->getDatabasePasswordColumn();
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

    /**
     * Determines if the given handler value is a class that contains the 'handle' method.
     *
     * @param mixed $handler
     *
     * @return bool
     */
    protected function isAttributeHandler($handler)
    {
        return is_string($handler) && class_exists($handler) && method_exists($handler, 'handle');
    }
}
