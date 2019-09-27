<?php

namespace LdapRecord\Laravel;

use RuntimeException;
use Illuminate\Support\Arr;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\ServiceProvider;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Auth\Events\Authenticated;
use LdapRecord\Laravel\Resolvers\UserResolver;
use LdapRecord\Laravel\Commands\Console\Import;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Resolvers\ResolverInterface;

class LdapRecordAuthServiceProvider extends ServiceProvider
{
    /**
     * Run service provider boot operations.
     *
     * @return void
     */
    public function boot()
    {
        // Register the lDAP auth provider.
        Auth::provider('ldap', function ($app, array $config) {
            return $this->makeUserProvider($app['hash'], $config);
        });

        if ($this->app->runningInConsole()) {
            $config = __DIR__.'/Config/auth.php';

            $this->publishes([
                $config => config_path('ldap_auth.php'),
            ]);
        }

        // Register the import command.
        $this->commands(Import::class);
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Bind the user resolver instance into the IoC.
        $this->app->bind(ResolverInterface::class, function () {
            return new UserResolver(
                $this->app->make(AdldapInterface::class)
            );
        });

        // Here we will register the event listener that will bind the users LDAP
        // model to their Eloquent model upon authentication (if configured).
        // This allows us to utilize their LDAP model right
        // after authentication has passed.
        Event::listen([Login::class, Authenticated::class], LdapRecord\Laravel\Listeners\BindsLdapUserModel::class);

        if ($this->isLogging()) {
            // If logging is enabled, we will set up our event listeners that
            // log each event fired throughout the authentication process.
            foreach ($this->getLoggingEvents() as $event => $listener) {
                Event::listen($event, $listener);
            }
        }
    }

    /**
     * Make a new LDAP user provider.
     *
     * @param Hasher $hasher
     * @param array  $config
     *
     * @throws RuntimeException
     *
     * @return \Illuminate\Contracts\Auth\UserProvider
     */
    protected function makeUserProvider(Hasher $hasher, array $config)
    {
        $provider = Config::get('ldap_auth.provider', DatabaseUserProvider::class);

        // The DatabaseUserProvider requires a model to be configured
        // in the configuration. We will validate this here.
        if (is_a($provider, DatabaseUserProvider::class, $allowString = true)) {
            // We will try to retrieve their model from the config file,
            // otherwise we will try to use the providers config array.
            $model = Config::get('ldap_auth.model') ?? Arr::get($config, 'model');

            if (! $model) {
                throw new RuntimeException(
                    "No model is configured. You must configure a model to use with the {$provider}."
                );
            }

            return new $provider($hasher, $model);
        }

        return new $provider();
    }

    /**
     * Determines if authentication requests are logged.
     *
     * @return bool
     */
    protected function isLogging()
    {
        return Config::get('ldap_auth.logging.enabled', false);
    }

    /**
     * Returns the configured authentication events to log.
     *
     * @return array
     */
    protected function getLoggingEvents()
    {
        return Config::get('ldap_auth.logging.events', []);
    }
}
