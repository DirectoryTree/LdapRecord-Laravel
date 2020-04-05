<?php

namespace LdapRecord\Laravel\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LdapRecord\Models\Model as LdapModel;

class PasswordHydrator extends Hydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate(LdapModel $user, EloquentModel $database)
    {
        if ($this->hasPasswordColumn()) {
            // We will ensure that we always have a password to
            // save to the user, even if one is not given.
            $password = $this->password() ?? Str::random();

            // If password sync is disabled, we will be sure to overwrite
            // the password so it is not saved to the eloquent model.
            if (! $this->isSyncingPasswords()) {
                $password = Str::random();
            }

            if ($this->passwordNeedsUpdate($database, $password)) {
                $this->setPassword($database, $password);
            }
        }
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
     * Get the password from the current data.
     *
     * @return string|null
     */
    protected function password()
    {
        return Arr::get($this->data, 'password');
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
