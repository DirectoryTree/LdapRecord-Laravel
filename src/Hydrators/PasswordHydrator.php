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
            $password = $this->isSyncingPasswords() ?
                $this->getPassword() : Str::random();

            if ($this->passwordNeedsUpdate($database, $password)) {
                $this->setPassword($database, $password);
            }
        }

        return $database;
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
        $password = $model->hasSetMutator($this->column()) ? $password : Hash::make($password);

        $model->setAttribute($this->column(), $password);
    }

    /**
     * Get the password to synchronize.
     *
     * @return string|null
     */
    protected function getPassword()
    {
        return Arr::get($this->config, 'password');
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

        if ($current !== null && $this->canSync()) {
            return ! Hash::check($password, $current);
        }

        return is_null($current);
    }

    /**
     * Determines if we're able to sync the models password.
     *
     * @return bool
     */
    protected function canSync()
    {
        return array_key_exists('password', $this->config) && $this->isSyncingPasswords();
    }

    /**
     * Determines if the developer has configured a password column.
     *
     * @return bool
     */
    protected function hasPasswordColumn()
    {
        return $this->column() !== false;
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
        return $model->getAttribute($this->column());
    }

    /**
     * Get the configured database password column to use.
     *
     * @return string|false
     */
    protected function column()
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
