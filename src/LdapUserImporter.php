<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Models\Model as LdapModel;

class LdapUserImporter
{
    /**
     * The LDAP user repository.
     *
     * @var LdapUserRepository
     */
    protected $users;

    /**
     * The Eloquent model to use for importing.
     *
     * @var string
     */
    protected $eloquentModel;

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
     * @param array  $attributes
     */
    public function __construct(string $eloquentModel, array $attributes)
    {
        $this->eloquentModel = $eloquentModel;
        $this->attributes = $attributes;
    }

    /**
     * Import / synchronize the LDAP user.
     *
     * @param LdapModel $user
     *
     * @return Model
     */
    public function run(LdapModel $user)
    {
        /** @var LdapAuthenticatable $model */
        $model = $this->getNewEloquentModel();

        // Here we'll try to locate our local user model from
        // the LDAP users model. If one isn't located,
        // we'll create a new one for them.
        $model = $this->locateLdapUserByModel($user, $model) ?: $model->newInstance();

        if (! $model->exists) {
            event(new Importing($user, $model));
        }

        event(new Synchronizing($user, $model));

        // Set the users LDAP domain and object GUID.
        $model->setLdapDomain($user->getConnection());
        $model->setLdapGuid($user->getConvertedGuid());

        foreach ($this->attributes as $modelField => $ldapField) {
            // If the field is a loaded class and contains a `handle()` method,
            // we need to construct the attribute handler.
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

        event(new Synchronized($user, $model));

        return $model;
    }

    /**
     * Retrieves an eloquent user by their GUID or their username.
     *
     * @param LdapModel $user
     *
     * @param Model|LdapAuthenticatable $model
     *
     * @return Model|null
     */
    protected function locateLdapUserByModel(LdapModel $user, Model $model)
    {
        $query = $model->newQuery();

        if ($query->getMacro('withTrashed')) {
            // If the withTrashed macro exists on our User model, then we must be
            // using soft deletes. We need to make sure we include these
            // results so we don't create duplicate user records.
            $query->withTrashed();
        }

        return $query
            ->where($model->getLdapGuidColumn(), '=', $user->getConvertedGuid())
            ->first();
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
