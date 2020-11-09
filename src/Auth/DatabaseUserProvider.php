<?php

namespace LdapRecord\Laravel\Auth;

use Closure;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Model;

class DatabaseUserProvider extends UserProvider
{
    /**
     * The LDAP user importer instance.
     *
     * @var LdapUserImporter
     */
    protected $importer;

    /**
     * The eloquent user provider.
     *
     * @var EloquentUserProvider
     */
    protected $eloquent;

    /**
     * The authenticating LDAP user.
     *
     * @var Model|null
     */
    protected $user;

    /**
     * The user resolver to use for finding the authenticating user.
     *
     * @var \Closure
     */
    protected $userResolver;

    /**
     * Whether falling back to Eloquent auth is being used.
     *
     * @var bool
     */
    protected $fallback = false;

    /**
     * Create a new LDAP user provider.
     *
     * @param LdapUserAuthenticator $auth
     * @param LdapUserRepository    $users
     * @param LdapUserImporter      $importer
     * @param EloquentUserProvider  $eloquent
     */
    public function __construct(
        LdapUserRepository $users,
        LdapUserAuthenticator $auth,
        LdapUserImporter $importer,
        EloquentUserProvider $eloquent
    ) {
        parent::__construct($users, $auth);

        $this->importer = $importer;
        $this->eloquent = $eloquent;

        $this->userResolver = function ($credentials) {
            return $this->users->findByCredentials($credentials);
        };
    }

    /**
     * Pass dynamic method calls into the Eloquent user provider.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->eloquent->{$method}(...$parameters);
    }

    /**
     * Get the LDAP user importer.
     *
     * @return LdapUserImporter
     */
    public function getLdapUserImporter()
    {
        return $this->importer;
    }

    /**
     * Set the authenticating LDAP user.
     *
     * @param Model $user
     */
    public function setAuthenticatingUser(Model $user)
    {
        $this->user = $user;
    }

    /**
     * Fallback to Eloquent authentication after failing to locate an LDAP user.
     *
     * @return void
     */
    public function shouldFallback()
    {
        $this->fallback = true;
    }

    /**
     * Set the callback to be used to resolve LDAP users.
     *
     * @param Closure $userResolver
     *
     * @return $this
     */
    public function resolveUsersUsing(Closure $userResolver)
    {
        $this->userResolver = $userResolver;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveById($identifier)
    {
        return $this->eloquent->retrieveById($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken($identifier, $token)
    {
        return $this->eloquent->retrieveByToken($identifier, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $this->eloquent->updateRememberToken($user, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials)
    {
        $user = rescue(function () use ($credentials) {
            return call_user_func($this->userResolver, $credentials);
        });

        // If an LDAP user is not located by their credentials and fallback
        // is enabled, we will attempt to locate the local database user
        // instead and perform validation on their password normally.
        if (! $user) {
            return isset($credentials['fallback'])
                ? $this->retrieveByCredentialsUsingEloquent($credentials)
                : null;
        }

        $this->setAuthenticatingUser($user);

        return $this->importer->run($user, $credentials);
    }

    /**
     * Retrieve the user from their credentials using Eloquent.
     *
     * @param array $credentials
     *
     * @throws \Exception
     *
     * @return Authenticatable|null
     */
    protected function retrieveByCredentialsUsingEloquent($credentials)
    {
        $this->shouldFallback();

        return $this->eloquent->retrieveByCredentials($credentials['fallback']);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $model, array $credentials)
    {
        // If an LDAP user has not been located, fallback is enabled, and
        // the given Eloquent model exists, we will attempt to validate
        // the users password normally via the Eloquent user provider.
        if (! $this->user instanceof Model) {
            return $this->fallback && $model->exists
                ? $this->eloquent->validateCredentials($model, $credentials)
                : false;
        }

        $this->auth->setEloquentModel($model);

        if (! $this->auth->attempt($this->user, $credentials['password'])) {
            return false;
        }

        $model->save();

        if ($model->wasRecentlyCreated) {
            event(new Imported($this->user, $model));
        }

        return true;
    }
}
