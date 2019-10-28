<?php

namespace LdapRecord\Laravel\Auth;

use LdapRecord\Models\Model;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\Commands\Importer;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\Commands\PasswordSync;
use LdapRecord\Laravel\Traits\ValidatesUsers;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\Events\AuthenticationSuccessful;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\AuthenticatedWithCredentials;

class DatabaseUserProvider implements UserProvider
{
    use ValidatesUsers;

    /**
     * The LDAP domain to use for authentication.
     *
     * @var Domain
     */
    protected $domain;

    /**
     * The fallback eloquent user provider.
     *
     * @var EloquentUserProvider
     */
    protected $fallback;

    /**
     * The currently authenticated LDAP user.
     *
     * @var Model|null
     */
    protected $user;

    /**
     * Create a new LDAP user provider.
     *
     * @param Hasher $hasher
     * @param Domain $domain
     */
    public function __construct(Hasher $hasher, Domain $domain)
    {
        $this->domain = $domain;
        $this->fallback = new EloquentUserProvider($hasher, $domain->getDatabaseModel());
    }


    /**
     * {@inheritDoc}
     */
    public function retrieveById($identifier)
    {
        return $this->fallback->retrieveById($identifier);
    }

    /**
     * {@inheritDoc}
     */
    public function retrieveByToken($identifier, $token)
    {
        return $this->fallback->retrieveByToken($identifier, $token);
    }

    /**
     * {@inheritDoc}
     */
    public function updateRememberToken(Authenticatable $user, $token)
    {
        $this->fallback->updateRememberToken($user, $token);
    }

    /**
     * {@inheritdoc}
     */
    public function retrieveByCredentials(array $credentials)
    {
        // Retrieve the LDAP user who is authenticating.
        $user = $this->domain->locate()->byCredentials($credentials);

        if ($user instanceof Model) {
            // Set the currently authenticating LDAP user.
            $this->user = $user;

            event(new DiscoveredWithCredentials($user));

            // Import / locate the local user account.
            return (new Importer($this->domain))->run($user);
        }

        if ($this->domain->isFallingBack()) {
            return $this->fallback->retrieveByCredentials($credentials);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function validateCredentials(Authenticatable $model, array $credentials)
    {
        if ($this->user instanceof Model) {
            if (!$this->domain->auth()->attempt($this->user, $credentials)) {
                // LDAP Authentication failed.
                return false;
            }

            event(new AuthenticatedWithCredentials($this->user, $model));

            // Here we will perform authorization on the LDAP user. If all
            // validation rules pass, we will allow the authentication
            // attempt. Otherwise, it is automatically rejected.
            if (!$this->getLdapUserValidator($this->user, $model)->passes()) {
                event(new AuthenticationRejected($this->user, $model));

                return false;
            }

            // Here we will synchronize / set the users password as they have
            // successfully passed authentication and validation rules.
            (new PasswordSync($this->domain))->run($model, $credentials);

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
            return $this->fallback->validateCredentials($model, $credentials);
        }

        return false;
    }
}
