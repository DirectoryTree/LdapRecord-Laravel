<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Testing\FakeDirectory;
use LdapRecord\Models\Entry;

class FakeDirectoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDirectory::setup('default');
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

    public function test_delete_attributes()
    {
        $model = tap(new TestModel, function ($model) {
            $model->cn = 'John Doe';
            $model->foo = 'bar';
            $model->baz = 'set';
            $model->save();
        });

        $model = TestModel::find($model->getDn());
        $this->assertEquals(['bar'], $model->foo);
        $this->assertEquals(['set'], $model->baz);

        $model->deleteAttributes($model->getDn(), ['foo', 'baz']);

        $model = TestModel::find($model->getDn());
        $this->assertNull($model->foo);
        $this->assertNull($model->baz);
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

    public function test_updating_by_adding_an_attribute_value()
    {
        $model = tap(new TestModel, function ($model) {
            $model->foo = ['bar'];
            $this->assertTrue($model->save());
        });

        $model->foo = array_merge($model->foo, ['baz']);
        $this->assertTrue($model->save());

        $model = TestModel::find($model->getDn());
        $this->assertEquals(['bar', 'baz'], $model->foo);
    }

    public function test_updating_by_removing_attribute()
    {
        $model = tap(new TestModel, function ($model) {
            $model->foo = ['bar'];
            $this->assertTrue($model->save());
        });

        $model->foo = null;
        $model->save();

        $model = TestModel::find($model->getDn());
        $this->assertNull($model->foo);
    }

    public function test_fresh()
    {
        $model = tap(new TestModel, function ($model) {
            $model->foo = ['bar'];
            $model->save();
        });

        $this->assertTrue($model->is($model->fresh()));
        $this->assertEquals($model, $model->fresh());
    }

    public function test_get()
    {
        $john = TestModel::create(['cn' => ['John']]);
        $jane = TestModel::create(['cn' => ['Jane']]);

        $models = TestModel::get();

        $this->assertCount(2, $models);
        $this->assertTrue($john->is($models[0]));
        $this->assertTrue($jane->is($models[1]));
        $this->assertEquals($john->getAttributes(), $models[0]->getAttributes());
        $this->assertEquals($jane->getAttributes(), $models[1]->getAttributes());
    }

    public function test_where_attribute()
    {
        $models = collect([
            TestModel::create(['cn' => ['John']]),
            TestModel::create(['cn' => ['Jane']]),
        ]);

        $results = TestModel::where('cn', '=', 'John')->get();
        $this->assertCount(1, $results);
        $this->assertTrue($models->first()->is($models->first()));
        $this->assertEmpty(TestModel::where('cn', '=', 'invalid')->get());
    }

    public function test_where_has()
    {
        $models = collect([
            TestModel::create(['cn' => ['John']]),
            TestModel::create(['cn' => ['Jane']]),
        ]);

        $results = TestModel::whereHas('cn')->get();

        $this->assertCount(2, $models);
        $this->assertTrue($models->first()->is($results->first()));
        $this->assertEmpty(TestModel::whereHas('invalid')->get());
    }

    public function test_where_has_multiple()
    {
        $models = collect([
            TestModel::create(['cn' => ['John'], 'sn' => 'Doe']),
            TestModel::create(['cn' => ['Jane']]),
        ]);

        $results = TestModel::whereHas('cn')->whereHas('sn')->get();
        $this->assertCount(1, $results);
        $this->assertTrue($models->first()->is($results->first()));
    }

    public function test_where_not_has()
    {
        TestModel::create(['cn' => ['John']]);
        TestModel::create(['cn' => ['Jane']]);
        TestModel::create(['sn' => ['Doe']]);

        $this->assertCount(1, TestModel::whereNotHas('cn')->get());
        $this->assertCount(2, TestModel::whereNotHas('sn')->get());
    }

    public function test_where_contains()
    {
        TestModel::create(['cn' => ['John']]);
        TestModel::create(['cn' => ['Jane']]);

        $this->assertCount(2, TestModel::whereContains('cn', 'J')->get());
        $this->assertCount(1, TestModel::whereContains('cn', 'Jo')->get());
        $this->assertCount(1, TestModel::whereContains('cn', 'ohn')->get());
    }

    public function test_where_starts_with()
    {
        TestModel::create(['cn' => ['John']]);
        TestModel::create(['cn' => ['Jane']]);
        TestModel::create(['cn' => ['Steve']]);

        $this->assertCount(2, TestModel::whereStartsWith('cn', 'J')->get());
        $this->assertCount(1, TestModel::whereStartsWith('cn', 'St')->get());
        $this->assertCount(0, TestModel::whereStartsWith('cn', 'teve')->get());

        $models = TestModel::whereStartsWith('cn', 'J')
                    ->whereStartsWith('cn', 'Ja')
                    ->get();

        $this->assertCount(1, $models);
    }

    public function test_where_ends_with()
    {
        TestModel::create(['cn' => ['John']]);
        TestModel::create(['cn' => ['Jen']]);
        TestModel::create(['cn' => ['Steve']]);

        $this->assertCount(2, TestModel::whereEndsWith('cn', 'n')->get());
        $this->assertCount(1, TestModel::whereEndsWith('cn', 'hn')->get());
        $this->assertCount(0, TestModel::whereEndsWith('cn', 'oh')->get());

        $models = TestModel::whereEndsWith('cn', 'n')
                    ->whereEndsWith('cn', 'hn')
                    ->get();

        $this->assertCount(1, $models);
    }
}

class TestModel extends Entry
{
    public static $objectClasses = ['one', 'two'];
}
