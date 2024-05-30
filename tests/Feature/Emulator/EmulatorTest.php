<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Database\Connection;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Testing\LdapDatabaseManager;
use LdapRecord\Laravel\Tests\TestCase;

class EmulatorTest extends TestCase
{
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $app['config']->set('ldap.default', 'default');
        $app['config']->set('ldap.connections.default', [
            'base_dn' => 'dc=local,dc=com',
            'use_tls' => true,
        ]);
    }

    public function test_sqlite_connection_is_setup()
    {
        DirectoryEmulator::setup('default');

        $db = app(LdapDatabaseManager::class);

        $this->assertCount(1, $db->getConnections());

        $connection = $db->getConnections()['default'];

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertEquals('sqlite', $connection->getConfig('driver'));
        $this->assertEquals(':memory:', $connection->getDatabaseName());
    }

    public function test_configuration_and_options_are_carried_over_to_emulated_connection()
    {
        $fake = DirectoryEmulator::setup('default');

        $this->assertTrue($fake->getLdapConnection()->isUsingTLS());
        $this->assertEquals('dc=local,dc=com', $fake->getConfiguration()->get('base_dn'));
    }
}
