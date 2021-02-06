<?php

namespace LdapRecord\Laravel\Auth;

use Closure;
use LdapRecord\Models\Model;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Events\Auth\Completed;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Import\UserSynchronizer;

class DatabaseUserProvider extends UserProvider
{
    /**
     * The LDAP user synchronizer instance.
     *
     * @var UserSynchronizer
     */
    protected $synchronizer;

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
     * @param UserSynchronizer      $synchronizer
     * @param EloquentUserProvider  $eloquent
     */
    public function __construct(
        LdapUserRepository $users,
        LdapUserAuthenticator $auth,
        UserSynchronizer $synchronizer,
        EloquentUserProvider $eloquent
    ) {
        parent::__construct($users, $auth);

        $this->synchronizer = $synchronizer;
        $this->eloquent = $eloquent;
    }

    /**
     * Get the LDAP user importer.
     *
     * @return UserSynchronizer
     */
    public function getLdapUserSynchronizer()
    {
        return $this->synchronizer;
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
        // If an LDAP user is not located by their credentials and fallback
        // is enabled, we will attempt to locate the local database user
        // instead and perform validation on their password normally.
        if (! $user = $this->fetchLdapUserByCredentials($credentials)) {
            return isset($credentials['fallback'])
                ? $this->retrieveByCredentialsUsingEloquent($credentials)
                : null;
        }

        $this->setAuthenticatingUser($user);

        return $this->synchronizer->run($user, $credentials);
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
    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        // If an LDAP user has not been located, fallback is enabled, and
        // the given Eloquent model exists, we will attempt to validate
        // the users password normally via the Eloquent user provider.
        if (! $this->user instanceof Model) {
            return $this->fallback && $user->exists
                ? $this->eloquent->validateCredentials($user, $credentials)
                : false;
        }

        $this->auth->setEloquentModel($user);

        if (! $this->auth->attempt($this->user, $credentials['password'])) {
            return false;
        }

        $user->save();

        event(new Saved($this->user, $user));

        if ($user->wasRecentlyCreated) {
            event(new Imported($this->user, $user));
        }

        event(new Completed($this->user, $user));

        return true;
    }
}
