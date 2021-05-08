<?php

namespace LdapRecord\Laravel;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Laravel\Auth\BindFailureListener;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\Commands\ImportLdapUsers;
use LdapRecord\Laravel\Events\LoggableEvent;
use LdapRecord\Laravel\Import\UserSynchronizer;

class LdapAuthServiceProvider extends ServiceProvider
{
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
        Event::listen('LdapRecord\Laravel\Events\*', function ($eventName, array $events) {
            collect($events)->filter(function ($event) {
                return $event instanceof LoggableEvent && $event->shouldLogEvent();
            })->each(function (LoggableEvent $event) {
                Log::log($event->getLogLevel(), $event->getLogMessage());
            });
        });
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
        return app(DatabaseUserProvider::class, [
            'users' => $this->makeLdapUserRepository($config),
            'auth' => $this->makeLdapUserAuthenticator($config),
            'eloquent' => $this->makeEloquentUserProvider($config),
            'synchronizer' => $this->makeLdapUserSynchronizer($config['database']),
        ]);
    }

    /**
     * Make a new plain LDAP user provider.
     *
     * @param array $config
     *
     * @return NoDatabaseUserProvider
     */
    protected function makePlainUserProvider(array $config)
    {
        return app(NoDatabaseUserProvider::class, [
            'users' => $this->makeLdapUserRepository($config),
            'auth' => $this->makeLdapUserAuthenticator($config),
        ]);
    }

    /**
     * Make a new Eloquent user provider.
     *
     * @param array $config
     *
     * @return EloquentUserProvider
     */
    protected function makeEloquentUserProvider($config)
    {
        return app(EloquentUserProvider::class, [
            'hasher' => $this->app->make('hash'),
            'model' => $config['database']['model'],
        ]);
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
        return app(LdapUserAuthenticator::class, ['rules' => $config['rules'] ?? []]);
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
        return app(LdapUserRepository::class, ['model' => $config['model']]);
    }

    /**
     * Make a new LDAP user importer.
     *
     * @param array $config
     *
     * @return UserSynchronizer
     */
    protected function makeLdapUserSynchronizer(array $config)
    {
        return app(UserSynchronizer::class, [
            'eloquentModel' => $config['model'],
            'config' => $config,
        ]);
    }
}
