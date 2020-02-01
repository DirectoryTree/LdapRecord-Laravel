<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Testing\FakeDirectory;
use LdapRecord\Models\Entry;

class EloquentFactoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDirectory::setup();
    }

    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $app['config']->set('ldap.default', 'default');
        $app['config']->set('ldap.connections.default', [
            'base_dn' => 'dc=local,dc=com',
        ]);
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
