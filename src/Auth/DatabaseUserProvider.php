<?php

namespace LdapRecord\Laravel\Auth;

use Closure;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Traits\ForwardsCalls;
use LdapRecord\Laravel\Events\Auth\Completed;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\Import\UserSynchronizer;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Model;

/** @mixin EloquentUserProvider */
class DatabaseUserProvider extends UserProvider
{
    use ForwardsCalls;

    /**
     * The LDAP user synchronizer instance.
     */
    protected UserSynchronizer $synchronizer;

    /**
     * The eloquent user provider.
     */
    protected EloquentUserProvider $eloquent;

    /**
     * The authenticating LDAP user.
     */
    protected ?Model $user = null;

    /**
     * Whether falling back to Eloquent auth is being used.
     */
    protected bool $fallback = false;

    /**
     * Create a new LDAP user provider.
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
     * Dynamically pass missing methods to the Eloquent user provider.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->forwardCallTo($this->eloquent, $method, $parameters);
    }

    /**
     * Get the Eloquent user provider.
     */
    public function eloquent(): EloquentUserProvider
    {
        return $this->eloquent;
    }

    /**
     * Get the LDAP user importer.
     */
    public function getLdapUserSynchronizer(): UserSynchronizer
    {
        return $this->synchronizer;
    }

    /**
     * Set the authenticating LDAP user.
     */
    public function setAuthenticatingUser(Model $user): void
    {
        $this->user = $user;
    }

    /**
     * Fallback to Eloquent authentication after failing to locate an LDAP user.
     */
    public function shouldFallback(): void
    {
        $this->fallback = true;
    }

    /**
     * Set the callback to be used to resolve LDAP users.
     */
    public function resolveUsersUsing(Closure $callback): static
    {
        $this->userResolver = $callback;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveById($identifier): ?Authenticatable
    {
        return $this->eloquent->retrieveById($identifier);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByToken($identifier, $token): ?Authenticatable
    {
        return $this->eloquent->retrieveByToken($identifier, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function updateRememberToken(Authenticatable $user, $token): void
    {
        $this->eloquent->updateRememberToken($user, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        $this->fallback = isset($credentials['fallback']);

        $fetch = function () use ($credentials) {
            return $this->fetchLdapUserByCredentials($credentials);
        };

        // If fallback is enabled, we want to make sure we catch
        // any exceptions that may occur so we do not interrupt
        // the fallback process and exit authentication early.
        $user = $this->fallback ? rescue($fetch) : value($fetch);

        // If the user was unable to be located and fallback is
        // enabled, we will attempt to fetch the database user
        // and perform validation on their password normally.
        if (! $user) {
            return $this->fallback
                ? $this->retrieveByCredentialsUsingEloquent($credentials['fallback'])
                : null;
        }

        $this->setAuthenticatingUser($user);

        return $this->synchronizer->run($user, $credentials);
    }

    /**
     * Retrieve the user from their credentials using Eloquent.
     *
     * @throws \Exception
     */
    protected function retrieveByCredentialsUsingEloquent(array $credentials): ?Authenticatable
    {
        return $this->eloquent->retrieveByCredentials($credentials);
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
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

    /**
     * {@inheritdoc}
     */
    public function rehashPasswordIfRequired(Authenticatable $user, array $credentials, bool $force = false): void
    {
        if (($this->synchronizer->getConfig()['password_column'] ?? 'password') !== false) {
            $this->eloquent->rehashPasswordIfRequired($user, $credentials, $force);
        }
    }
}
