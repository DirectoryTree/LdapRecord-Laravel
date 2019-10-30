<?php

namespace LdapRecord\Laravel\Database;

use UnexpectedValueException;
use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Models\Model as LdapModel;
use LdapRecord\Laravel\SynchronizedDomain;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class Importer
{
    /**
     * The scope to utilize for locating the LDAP user to synchronize.
     *
     * @var string
     */
    public static $scope = UserImportScope::class;

    /**
     * The LDAP domain to use for importing.
     *
     * @var SynchronizedDomain
     */
    protected $domain;

    /**
     * Sets the scope to use for locating LDAP users.
     *
     * @param $scope
     */
    public static function useScope($scope)
    {
        static::$scope = $scope;
    }

    /**
     * {@inheritDoc}
     */
    public function __construct(SynchronizedDomain $domain)
    {
        $this->domain = $domain;
    }

    /**
     * {@inheritDoc}
     */
    public function run(LdapModel $user) : Model
    {
        $model = $this->getNewDatabaseModel();

        // Here we'll try to locate our local user model from
        // the LDAP users model. If one isn't located,
        // we'll create a new one for them.
        $model = $this->locateLdapUserByModel($user, $model) ?: $model->newInstance();

        if (! $model->exists) {
            event(new Importing($user, $model));
        }

        event(new Synchronizing($user, $model));

        $this->sync($user, $model);

        event(new Synchronized($user, $model));

        return $model;
    }

    /**
     * Retrieves an eloquent user by their GUID or their username.
     *
     * @param LdapModel $user
     *
     * @param Model $model
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

        /** @var UserImportScope $scope */
        $scope = new static::$scope($this->domain, $user);

        $scope->apply($query, $model);

        return $query->first();
    }

    /**
     * Fills a models attributes by the specified Users attributes.
     *
     * @param LdapModel           $user
     * @param LdapAuthenticatable $model
     *
     * @return void
     */
    protected function sync(LdapModel $user, LdapAuthenticatable $model)
    {
        // Set the users LDAP object GUID and domain.
        $model->setLdapGuid($user->getConvertedGuid());
        $model->setLdapDomain($this->domain->getName());

        // Set the users username.
        $model->setAttribute(
            $this->domain->getDatabaseUsernameColumn(), $this->getUserUsername($user)
        );

        foreach ($this->domain->getSyncAttributes() as $modelField => $ldapField) {
            // If the field is a loaded class and contains a `handle()` method,
            // we need to construct the attribute handler.
            if ($this->isHandler($ldapField)) {
                // We will construct the attribute handler using Laravel's
                // IoC to allow developers to utilize application
                // dependencies in the constructor.
                /** @var mixed $handler */
                $handler = app($ldapField);

                $handler->handle($user, $model);
            } else {
                // We'll try to retrieve the value from the LDAP model. If the LDAP field is a string,
                // we'll assume the developer wants the attribute, or a null value. Otherwise,
                // the raw value of the LDAP field will be used.
                $model->{$modelField} = is_string($ldapField) ? $user->getFirstAttribute($ldapField) : $ldapField;
            }
        }
    }

    /**
     * Get a new domain database model.
     *
     * @return \Illuminate\Database\Eloquent\Model
     */
    protected function getNewDatabaseModel()
    {
        $model = $this->domain->getDatabaseModel();

        return new $model;
    }

    /**
     * Returns the LDAP users configured username.
     *
     * @param LdapModel $user
     *
     * @return string
     *
     * @throws UnexpectedValueException
     */
    protected function getUserUsername(LdapModel $user)
    {
        $username = $user->getFirstAttribute($this->domain->getAuthUsername());

        if (trim($username) == false) {
            throw new UnexpectedValueException(
                "Unable to locate a user without a {$this->domain->getAuthUsername()}"
            );
        }

        return $username;
    }

    /**
     * Determines if the given handler value is a class that contains the 'handle' method.
     *
     * @param mixed $handler
     *
     * @return bool
     */
    protected function isHandler($handler)
    {
        return is_string($handler) && class_exists($handler) && method_exists($handler, 'handle');
    }
}
