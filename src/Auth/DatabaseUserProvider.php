<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\AuthenticatedWithCredentials;
use LdapRecord\Laravel\Events\AuthenticationSuccessful;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;
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
     * The currently authenticated LDAP user.
     *
     * @var Model|null
     */
    protected $user;

    /**
     * Create a new LDAP user provider.
     *
     * @param LdapUserAuthenticator $auth
     * @param LdapUserRepository    $users
     * @param LdapUserImporter      $importer
     * @param EloquentUserProvider  $eloquentUserProvider
     */
    public function __construct(
        LdapUserRepository $users,
        LdapUserAuthenticator $auth,
        LdapUserImporter $importer,
        EloquentUserProvider $eloquentUserProvider
    ) {
        parent::__construct($users, $auth);

        $this->importer = $importer;
        $this->eloquent = $eloquentUserProvider;
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
        $user = $this->users->findByCredentials($credentials);

        if ($user instanceof Model) {
            $this->user = $user;

            event(new DiscoveredWithCredentials($user));

            return $this->importer->run($user);
        }

        if ($this->domain->isFallingBack()) {
            return $this->eloquent->retrieveByCredentials($credentials);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $model, array $credentials)
    {
        if ($this->user instanceof Model) {
            $this->auth->setEloquentModel($model);

            if (! $this->auth->attempt($this->user, $credentials['password'])) {
                return false;
            }

            event(new AuthenticatedWithCredentials($this->user, $model));

            // Here we will set the users password once they have
            // passed authentication and any validation rules.
            $this->domain->passwordSynchronizer()->run($model, $credentials['password']);

            $model->save();

            if ($model->wasRecentlyCreated) {
                // If the model was recently created, they
                // have been imported successfully.
                event(new Imported($this->user, $model));
            }

            event(new AuthenticationSuccessful($this->user, $model));

            return true;
        }

        if ($this->domain->isFallingBack() && $model->exists) {
            // If the user exists in our local database already and fallback is
            // enabled, we'll perform standard eloquent authentication.
            return $this->eloquent->validateCredentials($model, $credentials);
        }

        return false;
    }
}
