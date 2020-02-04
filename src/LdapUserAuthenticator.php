<?php

namespace LdapRecord\Laravel;

use LdapRecord\Laravel\Auth\Validator;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\AuthenticatedModelTrashed;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Models\Model;

class LdapUserAuthenticator
{
    use DetectsSoftDeletes;

    /**
     * The LDAP authentication rules.
     *
     * @var array
     */
    protected $rules = [];

    /**
     * The eloquent user model.
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $eloquentModel;

    /**
     * Constructor.
     *
     * @param array $rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;
    }

    /**
     * Set the eloquent model.
     *
     * @param \Illuminate\Database\Eloquent\Model $model
     *
     * @return $this
     */
    public function setEloquentModel($model)
    {
        $this->eloquentModel = $model;

        return $this;
    }

    /**
     * Attempt authenticating against the LDAP domain.
     *
     * @param Model  $user
     * @param string $password
     *
     * @return bool
     */
    public function attempt(Model $user, $password)
    {
        $this->attempting($user);

        if ($this->databaseModelIsTrashed()) {
            $this->trashed($user);

            return false;
        }

        if ($user->getConnection()->auth()->attempt($user->getDn(), $password)) {
            $this->passed($user);

            // Here we will perform authorization on the LDAP user. If all
            // validation rules pass, we will allow the authentication
            // attempt. Otherwise, it is automatically rejected.
            if (! $this->validator($user, $this->eloquentModel)->passes()) {
                $this->rejected($user);

                return false;
            }

            return true;
        }

        $this->failed($user);

        return false;
    }

    /**
     * Create a new user validator.
     *
     * @param \LdapRecord\Models\Model                 $user
     * @param \Illuminate\Database\Eloquent\Model|null $model
     *
     * @return Validator
     */
    protected function validator($user, $model = null)
    {
        return new Validator($this->rules($user, $model));
    }

    /**
     * Get the authentication rules for the domain.
     *
     * @param \LdapRecord\Models\Model                 $user
     * @param \Illuminate\Database\Eloquent\Model|null $model
     *
     * @return \Illuminate\Support\Collection
     */
    protected function rules($user, $model = null)
    {
        return collect($this->rules)->map(function ($rule) use ($user, $model) {
            return new $rule($user, $model);
        })->values();
    }

    /**
     * Fire the "attempting" event.
     *
     * @param Model $user
     */
    protected function attempting(Model $user)
    {
        event(new Authenticating($user, $user->getDn()));
    }

    /**
     * Fire the "passed" event.
     *
     * @param Model $user
     */
    protected function passed(Model $user)
    {
        event(new Authenticated($user, $this->eloquentModel));
    }

    /**
     * Fire the "trashed" event.
     *
     * @param Model $user
     */
    protected function trashed(Model $user)
    {
        event(new AuthenticatedModelTrashed($user, $this->eloquentModel));
    }

    /**
     * Fire the "failed" event.
     *
     * @param Model $user
     */
    protected function failed(Model $user)
    {
        event(new AuthenticationFailed($user, $this->eloquentModel));
    }

    /**
     * Fire the "rejected" event.
     *
     * @param Model $user
     */
    protected function rejected(Model $user)
    {
        event(new AuthenticationRejected($user, $this->eloquentModel));
    }

    /**
     * Determine if the database model is trashed.
     *
     * @return bool
     */
    protected function databaseModelIsTrashed()
    {
        return isset($this->eloquentModel) &&
            $this->isUsingSoftDeletes($this->eloquentModel) &&
            $this->eloquentModel->trashed();
    }
}
