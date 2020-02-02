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

    public function test_find()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';
        $this->assertNull(TestModel::find($dn));
    }

    public function test_create()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        tap(new TestModel, function ($model) {
            $model->cn = 'John Doe';
            $model->save();
        });

        $model = TestModel::find($dn);
        $this->assertInstanceOf(TestModel::class, $model);
        $this->assertEquals($model->getAttributes(), $model->getAttributes());
        $this->assertEquals($dn, $model->getDn());
        $this->assertTrue($model->exists);
        $this->assertNull(TestModel::find('cn=John Doe'));
    }

    public function test_delete()
    {
        $model = tap(new TestModel, function ($model) {
            $model->cn = 'John Doe';
            $model->save();
        });

        $this->assertTrue($model->delete());
        $this->assertFalse($model->exists);
        $this->assertNull(TestModel::find($model->getDn()));
    }

    public function test_update_adding_attribute()
    {
        $model = tap(new TestModel, function ($model) {
            $model->cn = 'John Doe';
            $model->save();
        });

        $model->foo = 'bar';

        $this->assertTrue($model->save());
        $this->assertEquals(['bar'], $model->foo);

        $model = TestModel::find($model->getDn());
        $this->assertEquals(['bar'], $model->foo);
    }
}

class TestModel extends Entry
{
    public static $objectClasses = ['one', 'two'];
}
