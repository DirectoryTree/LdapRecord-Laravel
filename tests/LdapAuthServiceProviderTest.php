<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Auth\Events\Login;
use Illuminate\Events\Dispatcher;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\AuthenticatedModelTrashed;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;
use LdapRecord\Laravel\Events\Authenticating;
use LdapRecord\Laravel\Events\AuthenticationFailed;
use LdapRecord\Laravel\Events\AuthenticationRejected;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Laravel\LdapAuthServiceProvider;

class LdapAuthServiceProviderTest extends TestCase
{
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $app['config']->set('ldap.logging', true);
    }

    public function test_migrations_are_publishable()
    {
        $this->artisan('vendor:publish', ['--provider' => LdapAuthServiceProvider::class, '--no-interaction' => true]);

        $migrationFile = database_path('migrations/'.date('Y_m_d_His', time()).'_add_ldap_columns_to_users_table.php');

        $this->assertFileExists($migrationFile);

        unlink($migrationFile);
    }

    public function test_model_binding_listener_is_setup()
    {
        /** @var Dispatcher $event */
        $dispatcher = app(Dispatcher::class);

        $this->assertTrue($dispatcher->hasListeners(Login::class));
        $this->assertTrue($dispatcher->hasListeners(Authenticated::class));
    }

    public function test_event_listeners_are_setup_when_logging_is_enabled()
    {
        /** @var Dispatcher $event */
        $dispatcher = app(Dispatcher::class);

        $events = [
            Importing::class,
            Imported::class,
            Synchronized::class,
            Synchronizing::class,
            Authenticated::class,
            Authenticating::class,
            AuthenticationFailed::class,
            AuthenticationRejected::class,
            DiscoveredWithCredentials::class,
            AuthenticatedWithWindows::class,
            AuthenticatedModelTrashed::class,
        ];

        foreach ($events as $event) {
            $this->assertTrue($dispatcher->hasListeners($event));
        }
    }
}
