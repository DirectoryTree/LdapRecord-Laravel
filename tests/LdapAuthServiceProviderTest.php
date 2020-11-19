<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Events\Dispatcher;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;
use LdapRecord\Laravel\Events\Ldap\Bound;
use LdapRecord\Laravel\Events\Ldap\Binding;
use LdapRecord\Laravel\Events\Ldap\BindFailed;
use LdapRecord\Laravel\Events\Auth\Rejected;
use LdapRecord\Laravel\Events\Auth\EloquentModelTrashed;
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

    public function test_event_listeners_are_setup_when_logging_is_enabled()
    {
        /** @var Dispatcher $event */
        $dispatcher = app(Dispatcher::class);

        $events = [
            Importing::class,
            Imported::class,
            Synchronized::class,
            Synchronizing::class,
            Bound::class,
            Binding::class,
            BindFailed::class,
            Rejected::class,
            DiscoveredWithCredentials::class,
            AuthenticatedWithWindows::class,
            EloquentModelTrashed::class,
        ];

        foreach ($events as $event) {
            $this->assertTrue($dispatcher->hasListeners($event));
        }
    }
}
