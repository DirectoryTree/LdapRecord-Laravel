<?php

namespace LdapRecord\Laravel;

use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\Commands\ImportDomain;
use LdapRecord\Laravel\Commands\MakeDomain;
use LdapRecord\Laravel\Listeners\BindsLdapUserModel;

class LdapAuthServiceProvider extends ServiceProvider
{
    /**
     * The events to log (if enabled).
     *
     * @var array
     */
    protected $events = [
        Events\Importing::class                 => Listeners\LogImport::class,
        Events\Synchronized::class              => Listeners\LogSynchronized::class,
        Events\Synchronizing::class             => Listeners\LogSynchronizing::class,
        Events\Authenticated::class             => Listeners\LogAuthenticated::class,
        Events\Authenticating::class            => Listeners\LogAuthentication::class,
        Events\AuthenticationFailed::class      => Listeners\LogAuthenticationFailure::class,
        Events\AuthenticationRejected::class    => Listeners\LogAuthenticationRejection::class,
        Events\AuthenticationSuccessful::class  => Listeners\LogAuthenticationSuccess::class,
        Events\DiscoveredWithCredentials::class => Listeners\LogDiscovery::class,
        Events\AuthenticatedWithWindows::class  => Listeners\LogWindowsAuth::class,
        Events\AuthenticatedModelTrashed::class => Listeners\LogTrashedModel::class,
    ];

    /**
     * Run service provider boot operations.
     *
     * @return void
     */
    public function boot()
    {
        $this->commands([ImportDomain::class]);

        Auth::provider('ldap', function ($app, array $config) {
            $users = $this->makeLdapUserRepository($config);
            $auth = $this->makeLdapUserAuthenticator($users, $config);

            if (array_key_exists('database', $config)) {
                return new DatabaseUserProvider(
                    $users,
                    $auth,
                    $this->makeLdapUserImporter($config['database']),
                    $app['hash']
                );
            }

            return new NoDatabaseUserProvider($users, $auth);
        });

        // Here we will register the event listener that will bind the users LDAP
        // model to their Eloquent model upon authentication (if configured).
        // This allows us to utilize their LDAP model right
        // after authentication has passed.
        Event::listen([Login::class, Authenticated::class], BindsLdapUserModel::class);

        if (config('ldap.logging', true)) {
            // If logging is enabled, we will set up our event listeners that
            // log each event fired throughout the authentication process.
            foreach ($this->events as $event => $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Make a new LDAP user authenticator.
     *
     * @param LdapUserRepository $repository
     * @param array              $config
     *
     * @return LdapUserAuthenticator
     */
    protected function makeLdapUserAuthenticator(LdapUserRepository $repository, array $config)
    {
        return new LdapUserAuthenticator($repository->createModel()->getConnection(), $config['rules'] ?? []);
    }

    /**
     * Make a new LDAP user repository.
     *
     * @param array $config
     *
     * @return LdapUserRepository
     */
    protected function makeLdapUserRepository(array $config)
    {
        return new LdapUserRepository($config['model'], $config['scopes']);
    }

    /**
     * Make a new LDAP user importer.
     *
     * @param array $config
     *
     * @return LdapUserImporter
     */
    protected function makeLdapUserImporter(array $config)
    {
        return new LdapUserImporter($config['model'], $config['sync_attributes']);
    }
}
