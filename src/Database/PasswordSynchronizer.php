<?php

namespace LdapRecord\Laravel\Database;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\SynchronizedDomain;

class PasswordSynchronizer
{
    /**
     * The LDAP domain.
     *
     * @var SynchronizedDomain
     */
    protected $domain;

    /**
     * Constructor.
     *
     * @param SynchronizedDomain $domain
     */
    public function __construct(SynchronizedDomain $domain)
    {
        $this->domain = $domain;
    }

    /**
     * Synchronize the models password.
     *
     * @param Model $model
     * @param array $credentials
     *
     * @return Model
     */
    public function run(Model $model, array $credentials = []) : Model
    {
        if ($this->hasPasswordColumn()) {
            $password = $this->canSync($credentials) ?
                $this->getPassword($credentials) : Str::random();

            if ($this->passwordNeedsUpdate($model, $password)) {
                $this->setPassword($model, $password);
            }
        }

        return $model;
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
    protected function passwordNeedsUpdate(Model $model, $password = null) : bool
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
    protected function hasPasswordColumn() : bool
    {
        return ! is_null($this->column());
    }

    /**
     * Get the password from the users credentials.
     *
     * @param array $credentials
     *
     * @return string|null
     */
    protected function getPassword(array $credentials = [])
    {
        return Arr::get($credentials, 'password');
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
     * Determine we are able to sync the models password with the given credentials.
     *
     * @param array $credentials
     *
     * @return bool
     */
    protected function canSync(array $credentials = []) : bool
    {
        return array_key_exists('password', $credentials) && $this->domain->isSyncingPasswords();
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
}
