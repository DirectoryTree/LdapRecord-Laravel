<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Connection;
use LdapRecord\Laravel\Testing\LdapDatabaseManager;
use LdapRecord\Laravel\Tests\TestCase;

class LdapDatabaseManagerTest extends TestCase
{
    public function test_database_can_be_initialized()
    {
        $manager = app(LdapDatabaseManager::class);
        $connection = $manager->connection('default');

        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertCount(1, $manager->getConnections());
        $this->assertArrayHasKey('default', $manager->getConnections());
    }

    public function test_database_connections_are_not_resolved_twice()
    {
        $manager = app(LdapDatabaseManager::class);

        $manager->connection('default');
        $manager->connection('default');

        $this->assertCount(1, $manager->getConnections());
        $this->assertArrayHasKey('default', $manager->getConnections());
    }

    public function test_initialized_database_sets_up_configuration()
    {
        app(LdapDatabaseManager::class)->connection('default');

        $this->assertEquals([
            'driver' => 'sqlite',
            'database' => ':memory:',
        ], config('database.connections.ldap_default'));
    }

    public function test_cached_database_can_be_initialized()
    {
        $manager = app(LdapDatabaseManager::class);

        $file = storage_path('framework/cache/ldap_directory.sqlite');

        file_put_contents($file, '');

        $manager->connection('default', ['database' => $file]);

        $this->assertFileExists($file);

        $this->assertEquals([
            'driver' => 'sqlite',
            'database' => $file,
        ], config('database.connections.ldap_default'));

        unlink($file);
    }

    public function test_tear_down_removes_tables()
    {
        $manager = app(LdapDatabaseManager::class);

        $schema = $manager->connection('default')->getSchemaBuilder();
        $this->assertTrue($schema->hasTable('ldap_objects'));

        $manager->teardown();
        $this->assertFalse($schema->hasTable('ldap_objects'));
    }

    public function test_tear_down_deletes_cached_database()
    {
        $manager = app(LdapDatabaseManager::class);

        $file = storage_path('framework/cache/ldap_directory.sqlite');

        file_put_contents($file, '');

        $manager->connection('default', ['database' => $file]);

        $this->assertFileExists($file);

        $manager->teardown();

        $this->assertFileDoesNotExist($file);
    }
}
