<?php

namespace LdapRecord\Laravel;

use Closure;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Collection;
use LdapRecord\Laravel\Auth\Validator;
use LdapRecord\Laravel\Events\Auth\BindFailed;
use LdapRecord\Laravel\Events\Auth\Binding;
use LdapRecord\Laravel\Events\Auth\Bound;
use LdapRecord\Laravel\Events\Auth\EloquentUserTrashed;
use LdapRecord\Laravel\Events\Auth\Rejected;
use LdapRecord\Models\Model;

class LdapUserAuthenticator
{
    use DetectsSoftDeletes;

    /**
     * The LDAP authentication rules.
     *
     * @var \LdapRecord\Laravel\Auth\Rule[]
     */
    protected array $rules = [];

    /**
     * The authenticator to use for validating the user's password.
     */
    protected Closure $authenticator;

    /**
     * The eloquent user model.
     */
    protected ?Eloquent $eloquentModel = null;

    /**
     * Constructor.
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
     */
    public function setEloquentModel(?Eloquent $model = null): static
    {
        $this->eloquentModel = $model;

        return $this;
    }

    /**
     * Attempt authenticating against the LDAP domain.
     */
    public function attempt(Model $user, ?string $password = null): bool
    {
        $this->attempting($user);

        if ($this->databaseModelIsTrashed()) {
            $this->trashed($user);

            return false;
        }

        // Here we will attempt to bind the authenticating LDAP
        // user to our connection to ensure their password is
        // correct, using the defined authenticator closure.
        if (! call_user_func($this->authenticator, $user, $password)) {
            $this->failed($user);

            return false;
        }

        // Now we will perform authorization on the LDAP user. If all
        // validation rules pass, we will allow the authentication
        // attempt. Otherwise, it is automatically rejected.
        if (! $this->validate($user)) {
            $this->rejected($user);

            return false;
        }

        $this->passed($user);

        return true;
    }

    /**
     * Attempt authentication using the given callback once.
     */
    public function attemptOnceUsing(Closure $callback, Model $user, ?string $password = null): bool
    {
        $authenticator = $this->authenticator;

        $result = $this->authenticateUsing($callback)->attempt($user, $password);

        $this->authenticator = $authenticator;

        return $result;
    }

    /**
     * Set the callback to use for authenticating users.
     */
    public function authenticateUsing(Closure $authenticator): static
    {
        $this->authenticator = $authenticator;

        return $this;
    }

    /**
     * Validate the given user against the authentication rules.
     */
    protected function validate(Model $user): bool
    {
        return $this->validator()->passes($user, $this->eloquentModel);
    }

    /**
     * Create a new user validator.
     */
    protected function validator(): Validator
    {
        return app(Validator::class, ['rules' => $this->rules()]);
    }

    /**
     * Get the authentication rules for the domain.
     */
    protected function rules(): Collection
    {
        return collect($this->rules)->map(fn ($rule) => app($rule))->values();
    }

    /**
     * Fire the "attempting" event.
     */
    protected function attempting(Model $user): void
    {
        event(new Binding($user, $this->eloquentModel));
    }

    /**
     * Fire the "passed" event.
     */
    protected function passed(Model $user): void
    {
        event(new Bound($user, $this->eloquentModel));
    }

    /**
     * Fire the "trashed" event.
     */
    protected function trashed(Model $user): void
    {
        event(new EloquentUserTrashed($user, $this->eloquentModel));
    }

    /**
     * Fire the "failed" event.
     */
    protected function failed(Model $user): void
    {
        event(new BindFailed($user, $this->eloquentModel));
    }

    /**
     * Fire the "rejected" event.
     */
    protected function rejected(Model $user): void
    {
        event(new Rejected($user, $this->eloquentModel));
    }

    /**
     * Determine if the database model is trashed.
     */
    protected function databaseModelIsTrashed(): bool
    {
        return isset($this->eloquentModel)
            && $this->isUsingSoftDeletes($this->eloquentModel)
            && $this->eloquentModel->trashed();
    }
}
