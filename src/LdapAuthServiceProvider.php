<?php

namespace LdapRecord\Laravel;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use LdapRecord\Laravel\Auth\BindFailureListener;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\Commands\ImportLdapUsers;
use LdapRecord\Laravel\Import\UserSynchronizer;

class LdapAuthServiceProvider extends ServiceProvider
{
    /**
     * Run service provider boot operations.
     */
    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang/', 'ldap');

        $this->registerCommands();
        $this->registerMigrations();
        $this->registerAuthProvider();
        $this->registerLoginControllerListeners();
    }

    /**
     * Register the LDAP auth commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([ImportLdapUsers::class]);
    }

    /**
     * Register the LDAP auth migrations.
     */
    protected function registerMigrations(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'migrations');
    }

    /**
     * Register the LDAP auth provider.
     */
    protected function registerAuthProvider(): void
    {
        Auth::provider('ldap', function ($app, array $config) {
            return array_key_exists('database', $config)
                ? $this->makeDatabaseUserProvider($config)
                : $this->makePlainUserProvider($config);
        });
    }

    /**
     * Registers the login controller listener to handle LDAP errors.
     */
    protected function registerLoginControllerListeners(): void
    {
        BindFailureListener::usingLaravelUi();
        BindFailureListener::usingLaravelJetstream();
    }

    /**
     * Get a new database user provider.
     */
    protected function makeDatabaseUserProvider(array $config): DatabaseUserProvider
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
     */
    protected function makePlainUserProvider(array $config): NoDatabaseUserProvider
    {
        return app(NoDatabaseUserProvider::class, [
            'users' => $this->makeLdapUserRepository($config),
            'auth' => $this->makeLdapUserAuthenticator($config),
        ]);
    }

    /**
     * Make a new Eloquent user provider.
     */
    protected function makeEloquentUserProvider(array $config): EloquentUserProvider
    {
        return app(EloquentUserProvider::class, [
            'hasher' => $this->app->make('hash'),
            'model' => $config['database']['model'],
        ]);
    }

    /**
     * Make a new LDAP user authenticator.
     */
    protected function makeLdapUserAuthenticator(array $config): LdapUserAuthenticator
    {
        return app(LdapUserAuthenticator::class, ['rules' => $config['rules'] ?? []]);
    }

    /**
     * Make a new LDAP user repository.
     */
    protected function makeLdapUserRepository(array $config): LdapUserRepository
    {
        return app(LdapUserRepository::class, [
            'model' => $config['model'],
            'scopes' => $config['scopes'] ?? [],
        ]);
    }

    /**
     * Make a new LDAP user importer.
     */
    protected function makeLdapUserSynchronizer(array $config): UserSynchronizer
    {
        return app(UserSynchronizer::class, [
            'eloquentModel' => $config['model'],
            'config' => $config,
        ]);
    }
}
