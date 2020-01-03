<?php

namespace LdapRecord\Laravel;

use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\Validation\Validator;
use LdapRecord\Models\Model;

class LdapUserAuthenticator
{
    /**
     * The LDAP connection.
     *
     * @var Guard
     */
    protected $connection;

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
     * @param Connection $connection
     * @param array      $rules
     */
    public function __construct(Connection $connection, array $rules = [])
    {
        $this->connection = $connection;
        $this->rules = $rules;
    }

    /**
     * Attempt authenticating against the domain.
     *
     * @param Model  $user
     * @param string $password
     *
     * @return bool
     */
    public function attempt(Model $user, $password)
    {
        event(new Authenticating($user, $user->getDn()));

        if ($this->connection->auth()->attempt($user->getDn(), $password)) {
            event(new Authenticated($user));

            // Here we will perform authorization on the LDAP user. If all
            // validation rules pass, we will allow the authentication
            // attempt. Otherwise, it is automatically rejected.
            if (! $this->validator($user, $this->eloquentModel)->passes()) {
                event(new AuthenticationRejected($user, $this->eloquentModel));

                return false;
            }

            return true;
        }

        event(new AuthenticationFailed($user));

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
}
