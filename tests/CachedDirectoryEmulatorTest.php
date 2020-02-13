<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Testing\EloquentFactory;
use LdapRecord\Models\ActiveDirectory\User;

class CachedDirectoryEmulatorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Make sure we clear any cached files between each test.
        DirectoryEmulator::teardown();
    }

    protected function tearDown(): void
    {
        // Reset the emulator to use the memory SQLite driver.
        DirectoryEmulator::useMemoryDirectory();

        parent::tearDown();
    }

    public function test_caching_is_disabled_by_default()
    {
        DirectoryEmulator::setup('default');
        $this->assertFalse(file_exists(EloquentFactory::getCacheFilePath()));
    }

    public function test_caching_can_be_setup_and_used()
    {
        DirectoryEmulator::useCachedDirectory();
        DirectoryEmulator::setup('default');
        $this->assertTrue(file_exists(EloquentFactory::getCacheFilePath()));
        $this->assertInstanceOf(User::class, User::create(['cn' => 'John Doe']));
    }
}
