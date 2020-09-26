<?php

namespace LdapRecord\Laravel;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Laravel\Auth\BindFailureListener;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\Commands\ImportLdapUsers;

class LdapAuthServiceProvider extends ServiceProvider
{
    /**
     * The events to log (if enabled).
     *
     * @var array
     */
    protected $events = [
        Events\Importing::class                 => Listeners\LogImporting::class,
        Events\Imported::class                  => Listeners\LogImported::class,
        Events\Synchronized::class              => Listeners\LogSynchronized::class,
        Events\Synchronizing::class             => Listeners\LogSynchronizing::class,
        Events\DeletedMissing::class            => Listeners\LogDeletedMissing::class,
        Events\Authenticated::class             => Listeners\LogAuthenticated::class,
        Events\Authenticating::class            => Listeners\LogAuthentication::class,
        Events\AuthenticationFailed::class      => Listeners\LogAuthenticationFailure::class,
        Events\AuthenticationRejected::class    => Listeners\LogAuthenticationRejection::class,
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
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang/', 'ldap');

        $this->registerCommands();
        $this->registerMigrations();
        $this->registerAuthProvider();
        $this->registerEventListeners();
        $this->registerLoginControllerListeners();
    }

    /**
     * Register the LDAP auth commands.
     *
     * @return void
     */
    protected function registerCommands()
    {
        $this->commands([ImportLdapUsers::class]);
    }

    /**
     * Register the LDAP auth migrations.
     *
     * @return void
     */
    protected function registerMigrations()
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        if (! class_exists('AddLdapColumnsToUsersTable')) {
            $this->publishes([
                __DIR__.'/../database/migrations/add_ldap_columns_to_users_table.php.stub' => database_path('migrations/'.date('Y_m_d_His', time()).'_add_ldap_columns_to_users_table.php'),
            ], 'migrations');
        }
    }

    /**
     * Register the LDAP auth provider.
     *
     * @return void
     */
    protected function registerAuthProvider()
    {
        Auth::provider('ldap', function ($app, array $config) {
            return array_key_exists('database', $config)
                ? $this->makeDatabaseUserProvider($config)
                : $this->makePlainUserProvider($config);
        });
    }

    /**
     * Registers the LDAP event listeners.
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        if (config('ldap.logging', true)) {
            // If logging is enabled, we will set up our event listeners that
            // log each event fired throughout the authentication process.
            foreach ($this->events as $event => $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Registers the login controller listener to handle LDAP errors.
     *
     * @return void
     */
    protected function registerLoginControllerListeners()
    {
        BindFailureListener::usingLaravelUi();
        BindFailureListener::usingLaravelJetstream();
    }

    /**
     * Get a new database user provider.
     *
     * @param array $config
     *
     * @return DatabaseUserProvider
     */
    protected function makeDatabaseUserProvider(array $config)
    {
        return new DatabaseUserProvider(
            $this->makeLdapUserRepository($config),
            $this->makeLdapUserAuthenticator($config),
            $this->makeLdapUserImporter($config['database']),
            new EloquentUserProvider($this->app->make('hash'), $config['database']['model'])
        );
    }

    /**
     * Get a new plain LDAP user provider.
     *
     * @param array $config
     *
     * @return NoDatabaseUserProvider
     */
    protected function makePlainUserProvider(array $config)
    {
        return new NoDatabaseUserProvider(
            $this->makeLdapUserRepository($config),
            $this->makeLdapUserAuthenticator($config)
        );
    }

    /**
     * Make a new LDAP user authenticator.
     *
     * @param array $config
     *
     * @return LdapUserAuthenticator
     */
    protected function makeLdapUserAuthenticator(array $config)
    {
        return new LdapUserAuthenticator($config['rules'] ?? []);
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
        return new LdapUserRepository($config['model']);
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
        return new LdapUserImporter($config['model'], $config);
    }
}
