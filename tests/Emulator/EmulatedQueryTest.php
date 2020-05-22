<?php

namespace LdapRecord\Laravel\Tests\Emulator;

use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\TestCase;

class EmulatedQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DirectoryEmulator::setup('default');
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::teardown();

        parent::tearDown();
    }

    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $app['config']->set('ldap.default', 'default');
        $app['config']->set('ldap.connections.default', [
            'base_dn' => 'dc=local,dc=com',
        ]);
    }

    public function test_insert()
    {
        $query = Container::getConnection('default')->query();

        $dn = 'cn=John Doe,dc=local,dc=com';
        $attributes = ['objectclass' => ['foo', 'bar']];

        $this->assertTrue($query->insert($dn, $attributes));

        $result = $query->first();
        $this->assertEquals($dn, $result['dn'][0]);
        $this->assertEquals($attributes['objectclass'], $result['objectclass']);
    }

    public function test_find()
    {
        $query = Container::getConnection('default')->query();

        $this->assertNull($query->find('foo'));

        $dn = 'cn=John Doe,dc=local,dc=com';
        $attributes = ['objectclass' => ['foo', 'bar']];

        $query->insert($dn, $attributes);

        $record = $query->find($dn);

        $this->assertEquals($dn, $record['dn'][0]);
        $this->assertEquals($attributes['objectclass'], $record['objectclass']);
    }

    public function test_get()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', $attributes);
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', $attributes);

        $this->assertCount(2, $results = $query->get());

        $this->assertEquals($john, $results[0]['dn'][0]);
        $this->assertEquals($attributes['objectclass'], $results[0]['objectclass']);

        $this->assertEquals($jane, $results[1]['dn'][0]);
        $this->assertEquals($attributes['objectclass'], $results[1]['objectclass']);
    }
}
