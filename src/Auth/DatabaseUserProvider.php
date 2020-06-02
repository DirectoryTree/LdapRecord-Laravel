<?php

namespace LdapRecord\Laravel\Auth;

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
     * Whether falling back to Eloquent auth is enabled.
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
     * @param bool                  $fallback
     */
    public function __construct(
        LdapUserRepository $users,
        LdapUserAuthenticator $auth,
        LdapUserImporter $importer,
        EloquentUserProvider $eloquent,
        $fallback = false
    ) {
        parent::__construct($users, $auth);

        $this->importer = $importer;
        $this->eloquent = $eloquent;
        $this->fallback = $fallback;
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
        if (! $user = $this->users->findByCredentials($credentials)) {
            return $this->fallback
                ? $this->eloquent->retrieveByCredentials($credentials)
                : null;
        }

        $this->setAuthenticatingUser($user);

        return $this->importer->run($user, $credentials);
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

        if ($model->save() && $model->wasRecentlyCreated) {
            // If the model was recently created, they
            // have been imported successfully.
            event(new Imported($this->user, $model));
        }

        return true;
    }
}
