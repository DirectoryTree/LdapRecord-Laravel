<?php

namespace LdapRecord\Laravel\Tests\Feature\Commands;

use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Models\Entry;

class GetRootDseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DirectoryEmulator::setup();
    }

    public function tearDown(): void
    {
        DirectoryEmulator::tearDown();

        parent::tearDown();
    }

    public function test_command_displays_root_dse_attributes()
    {
        RootDse::create([
            'objectclass' => ['*'],
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $this->artisan('ldap:rootdse')
            ->expectsOutputToContain('foo')
            ->expectsOutputToContain('bar')
            ->expectsOutputToContain('baz')
            ->expectsOutputToContain('zal')
            ->assertSuccessful();
    }

    public function test_command_displays_only_requested_attributes()
    {
        RootDse::create([
            'objectclass' => ['*'],
            'foo' => 'bar',
            'baz' => 'zal',
            'zee' => 'bur',
        ]);

        $this->artisan('ldap:rootdse', ['--attributes' => 'foo,baz'])
            ->expectsOutputToContain('foo')
            ->expectsOutputToContain('bar')
            ->expectsOutputToContain('baz')
            ->expectsOutputToContain('zal')
            ->doesntExpectOutputToContain('zee')
            ->doesntExpectOutputToContain('bur')
            ->assertSuccessful();
    }

    public function test_command_displays_no_attributes_error_when_rootdse_is_empty_and_attributes_were_requested()
    {
        RootDse::create([
            'objectclass' => ['*'],
        ]);

        $this->artisan('ldap:rootdse', ['--attributes' => 'foo,bar'])
            ->expectsOutputToContain('Attributes [foo, bar] were not found in the Root DSE record.')
            ->assertFailed();
    }
}

class RootDse extends Entry
{
    public function getCreatableDn($name = null, $attribute = null)
    {
        return null;
    }
}
