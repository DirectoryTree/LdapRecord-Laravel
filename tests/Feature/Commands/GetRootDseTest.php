<?php

namespace LdapRecord\Laravel\Tests\Feature\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Models\Entry;

class GetRootDseTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DirectoryEmulator::setup();

        $this->withoutMockingConsoleOutput();
    }

    public function test_command_displays_root_dse_attributes()
    {
        RootDse::create([
            'objectclass' => ['*'],
            'foo' => 'bar',
            'baz' => 'zal',
        ]);

        $code = $this->artisan('ldap:rootdse');

        $this->assertEquals(0, $code);

        $output = Artisan::output();

        $this->assertStringContainsString('foo', $output);
        $this->assertStringContainsString('bar', $output);
        $this->assertStringContainsString('baz', $output);
        $this->assertStringContainsString('zal', $output);
    }

    public function test_command_displays_only_requested_attributes()
    {
        RootDse::create([
            'objectclass' => ['*'],
            'foo' => 'bar',
            'baz' => 'zal',
            'zee' => 'bur',
        ]);

        $code = $this->artisan('ldap:rootdse', ['--attributes' => 'foo,baz']);

        $this->assertEquals(0, $code);

        $output = Artisan::output();

        $this->assertStringContainsString('foo', $output);
        $this->assertStringContainsString('bar', $output);
        $this->assertStringContainsString('baz', $output);
        $this->assertStringContainsString('zal', $output);

        $this->assertStringNotContainsString('zee', $output);
        $this->assertStringNotContainsString('bur', $output);
    }

    public function test_command_displays_no_attributes_error_when_rootdse_is_empty_and_attributes_were_requested()
    {
        RootDse::create([
            'objectclass' => ['*'],
        ]);

        $code = $this->artisan('ldap:rootdse', ['--attributes' => 'foo,bar']);

        $this->assertEquals(Command::FAILURE, $code);

        $output = Artisan::output();

        $this->assertStringContainsString('Attributes [foo, bar] were not found in the Root DSE record.', $output);
    }
}

class RootDse extends Entry
{
    public function getCreatableDn(?string $name = null, ?string $attribute = null): string
    {
        return '';
    }
}
