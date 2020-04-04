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
        if ($user = $this->users->findByCredentials($credentials)) {
            $this->setAuthenticatingUser($user);

            return $this->importer->run($user, $credentials);
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

            if ($model->save() && $model->wasRecentlyCreated) {
                // If the model was recently created, they
                // have been imported successfully.
                event(new Imported($this->user, $model));
            }

            return true;
        }

        return false;
    }
}
