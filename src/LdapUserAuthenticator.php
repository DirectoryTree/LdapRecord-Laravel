<?php

namespace LdapRecord\Laravel;

use Closure;
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
     * The authenticator to use for validating the users password.
     *
     * @var Closure
     */
    protected $authenticator;

    /**
     * Constructor.
     *
     * @param array $rules
     */
    public function __construct(array $rules = [])
    {
        $this->rules = $rules;

        $this->authenticator = function (Model $user, $password) {
            return $user->getConnection()->auth()->attempt($user->getDn(), $password);
        };
    }

    /**
     * Set the authenticating eloquent model.
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

        if (call_user_func($this->authenticator, $user, $password)) {
            // Here we will perform authorization on the LDAP user. If all
            // validation rules pass, we will allow the authentication
            // attempt. Otherwise, it is automatically rejected.
            if (! $this->validate($user)) {
                $this->rejected($user);

                return false;
            }

            $this->passed($user);

            return true;
        }

        $this->failed($user);

        return false;
    }

    /**
     * Attempt authentication using the given callback once.
     *
     * @param Closure     $callback
     * @param Model       $user
     * @param string|null $password
     *
     * @return bool
     */
    public function attemptOnceUsing(Closure $callback, Model $user, $password = null)
    {
        $authenticator = $this->authenticator;

        $result = $this->authenticateUsing($callback)->attempt($user, $password);

        $this->authenticator = $authenticator;

        return $result;
    }

    /**
     * Set the callback to use for authenticating users.
     *
     * @param Closure $authenticator
     *
     * @return $this
     */
    public function authenticateUsing(Closure $authenticator)
    {
        $this->authenticator = $authenticator;

        return $this;
    }

    /**
     * Validate the given user against the authentication rules.
     *
     * @param Model $user
     *
     * @return bool
     */
    protected function validate(Model $user)
    {
        return $this->validator($user, $this->eloquentModel)->passes();
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
