<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Testing\EloquentFactory;
use LdapRecord\Laravel\Testing\FakeDirectory;
use LdapRecord\Models\Entry;

class EloquentFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDirectory::setup();

        config()->set('ldap.default', 'default');
        config('ldap.connections.default', [
            'base_dn' => 'dc=local,dc=com',
        ]);
    }

    protected function tearDown(): void
    {
        EloquentFactory::teardown();

        parent::tearDown();
    }

    public function test_create()
    {
        $entry = new TestModel();
        $entry->cn = 'John Doe';
        $entry->testing = 'test';
        $entry->setDn('cn=John Doe,dc=local,dc=com');

        $this->assertTrue($entry->save());

        $model = TestModel::first();

        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertTrue($model->exists);
        $this->assertEquals($model->getDn(), $entry->getDn());
        $this->assertEquals($entry->getAttributes(), $model->getAttributes());
    }
}

class TestModel extends Entry
{
    public static $objectClasses = ['one', 'two'];
}
