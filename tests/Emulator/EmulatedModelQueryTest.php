<?php

namespace LdapRecord\Laravel\Tests\Emulator;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Entry;
use Ramsey\Uuid\Uuid;

class EmulatedModelQueryTest extends TestCase
{
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $app['config']->set('ldap.default', 'default');
        $app['config']->set('ldap.connections.default', [
            'base_dn' => 'dc=local,dc=com',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        DirectoryEmulator::setup();
    }

    public function test_file_database_can_be_used()
    {
        $path = storage_path('test.sqlite');

        DirectoryEmulator::setup('default', ['database' => $path]);

        $this->assertFileExists($path);
    }

    public function test_find()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';
        $this->assertNull(TestModelStub::find($dn));

        $user = TestModelStub::create(['cn' => 'John Doe']);
        TestModelStub::create(['cn' => 'Jane Doe']);
        $this->assertTrue($user->is(TestModelStub::find($dn)));
    }

    public function test_find_by_guid()
    {
        $guid = Uuid::uuid4()->toString();
        $this->assertNull(User::findByGuid($guid));

        $user = User::create(['cn' => 'John Doe', 'objectguid' => $guid]);
        $this->assertTrue($user->is(User::findByGuid($guid)));
    }

    public function test_create()
    {
        $dn = 'cn=John Doe,dc=local,dc=com';

        tap(new TestModelStub, function ($model) {
            $model->cn = 'John Doe';
            $model->save();
        });

        $model = TestModelStub::find($dn);
        $this->assertInstanceOf(TestModelStub::class, $model);
        $this->assertEquals($model->getAttributes(), $model->getAttributes());
        $this->assertEquals($dn, $model->getDn());
        $this->assertTrue($model->exists);
        $this->assertNull(TestModelStub::find('cn=John Doe'));
    }

    public function test_create_attributes()
    {
        $model = TestModelStub::create(['cn' => 'John']);
        $this->assertNull($model->sn);

        $model->insertAttributes($model->getDn(), ['sn' => 'Doe']);

        $this->assertEquals('Doe', $model->fresh()->sn[0]);
    }

    public function test_rename()
    {
        $model = TestModelStub::create(['cn' => 'John Doe']);

        $this->assertEquals('cn=John Doe,dc=local,dc=com', $model->getDn());
        $this->assertTrue($model->rename('cn=Jane Doe', 'dc=local,dc=com'));
        $this->assertEquals('cn=Jane Doe,dc=local,dc=com', $model->getDn());

        $this->assertTrue($model->rename('cn=Jane Doe', 'ou=Users,dc=local,dc=com'));
        $this->assertEquals('cn=Jane Doe,ou=Users,dc=local,dc=com', $model->getDn());
    }

    public function test_delete()
    {
        $model = tap(new TestModelStub, function ($model) {
            $model->cn = 'John Doe';
            $model->save();
        });

        $this->assertTrue($model->delete());
        $this->assertFalse($model->exists);
        $this->assertNull(TestModelStub::find($model->getDn()));
    }

    public function test_delete_attributes()
    {
        $model = tap(new TestModelStub, function ($model) {
            $model->cn = 'John Doe';
            $model->foo = 'bar';
            $model->baz = 'set';
            $model->save();
        });

        $model = TestModelStub::find($model->getDn());
        $this->assertEquals(['bar'], $model->foo);
        $this->assertEquals(['set'], $model->baz);

        $model->deleteAttributes($model->getDn(), ['foo', 'baz']);

        $model = TestModelStub::find($model->getDn());
        $this->assertNull($model->foo);
        $this->assertNull($model->baz);
    }

    public function test_update_adding_attribute()
    {
        $model = tap(new TestModelStub, function ($model) {
            $model->cn = 'John Doe';
            $model->save();
        });

        $model->foo = 'bar';

        $this->assertTrue($model->save());
        $this->assertEquals(['bar'], $model->foo);

        $model = TestModelStub::find($model->getDn());
        $this->assertEquals(['bar'], $model->foo);
    }

    public function test_updating_by_adding_an_attribute_value()
    {
        $model = tap(new TestModelStub, function ($model) {
            $model->foo = ['bar'];
            $this->assertTrue($model->save());
        });

        $model->foo = array_merge($model->foo, ['baz']);
        $this->assertTrue($model->save());

        $model = TestModelStub::find($model->getDn());
        $this->assertEquals(['bar', 'baz'], $model->foo);
    }

    public function test_updating_by_removing_attribute()
    {
        $model = tap(new TestModelStub, function ($model) {
            $model->foo = ['bar'];
            $this->assertTrue($model->save());
        });

        $model->foo = null;
        $model->save();

        $model = TestModelStub::find($model->getDn());
        $this->assertNull($model->foo);
    }

    public function test_updating_attributes()
    {
        $model = TestModelStub::create(['cn' => 'John', 'sn' => 'Doe']);
        $this->assertEquals('Doe', $model->sn[0]);

        $model->updateAttributes($model->getDn(), ['sn' => []]);
        $this->assertNull($model->fresh()->sn);
    }

    public function test_fresh()
    {
        $model = tap(new TestModelStub, function ($model) {
            $model->foo = ['bar'];
            $model->save();
        });

        $this->assertTrue($model->is($model->fresh()));
        $this->assertEquals($model, $model->fresh());
    }

    public function test_select()
    {
        TestModelStub::create([
            'cn' => 'John',
            'sn' => 'Doe',
        ]);

        $user = TestModelStub::select('cn')->first();
        $this->assertNull($user->sn);
        $this->assertEquals(['John'], $user->cn);

        $user = TestModelStub::first('cn');
        $this->assertNull($user->sn);
        $this->assertEquals(['John'], $user->cn);

        $user = TestModelStub::get('cn')->first();
        $this->assertNull($user->sn);
        $this->assertEquals(['John'], $user->cn);
    }

    public function test_get()
    {
        $john = TestModelStub::create(['cn' => ['John']]);
        $jane = TestModelStub::create(['cn' => ['Jane']]);

        $models = TestModelStub::get();

        $this->assertCount(2, $models);
        $this->assertTrue($john->is($models[0]));
        $this->assertTrue($jane->is($models[1]));
        $this->assertEquals($john->getAttributes(), $models[0]->getAttributes());
        $this->assertEquals($jane->getAttributes(), $models[1]->getAttributes());
    }

    public function test_get_only_returns_matching_object_classes()
    {
        TestModelStub::create(['cn' => ['John']]);

        $model = new class extends Entry {
            public static $objectClasses = ['three', 'four'];
        };

        $this->assertCount(0, $model::get());
        $this->assertCount(1, TestModelStub::get());
    }

    public function test_where_attribute()
    {
        $models = collect([
            TestModelStub::create(['cn' => ['John']]),
            TestModelStub::create(['cn' => ['Jane']]),
        ]);

        $results = TestModelStub::where('cn', '=', 'John')->get();
        $this->assertCount(1, $results);
        $this->assertTrue($models->first()->is($models->first()));
        $this->assertEmpty(TestModelStub::where('cn', '=', 'invalid')->get());
    }

    public function test_where_has()
    {
        $models = collect([
            TestModelStub::create(['cn' => ['John']]),
            TestModelStub::create(['cn' => ['Jane']]),
            TestModelStub::create(['sn' => ['Jen']]),
        ]);

        $results = TestModelStub::whereHas('cn')->get();

        $this->assertCount(2, $results);
        $this->assertTrue($models->first()->is($results->first()));
        $this->assertEmpty(TestModelStub::whereHas('invalid')->get());
    }

    public function test_where_has_multiple()
    {
        $models = collect([
            TestModelStub::create(['cn' => ['John'], 'sn' => 'Doe']),
            TestModelStub::create(['cn' => ['Jane']]),
        ]);

        $results = TestModelStub::whereHas('cn')->whereHas('sn')->get();
        $this->assertCount(1, $results);
        $this->assertTrue($models->first()->is($results->first()));
    }

    public function test_where_not_has()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jane']]);
        TestModelStub::create(['sn' => ['Doe']]);

        $this->assertCount(1, TestModelStub::whereNotHas('cn')->get());
        $this->assertCount(2, TestModelStub::whereNotHas('sn')->get());
    }

    public function test_where_contains()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jane']]);

        $this->assertCount(2, TestModelStub::whereContains('cn', 'J')->get());
        $this->assertCount(1, TestModelStub::whereContains('cn', 'Jo')->get());
        $this->assertCount(1, TestModelStub::whereContains('cn', 'ohn')->get());
    }

    public function test_where_not_contains()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jane']]);

        $this->assertCount(0, TestModelStub::whereNotContains('cn', 'J')->get());
        $this->assertCount(1, TestModelStub::whereNotContains('cn', 'Jo')->get());
        $this->assertCount(1, TestModelStub::whereNotContains('cn', 'ohn')->get());
    }

    public function test_where_starts_with()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jane']]);
        TestModelStub::create(['cn' => ['Steve']]);

        $this->assertCount(2, TestModelStub::whereStartsWith('cn', 'J')->get());
        $this->assertCount(1, TestModelStub::whereStartsWith('cn', 'St')->get());
        $this->assertCount(0, TestModelStub::whereStartsWith('cn', 'teve')->get());

        $models = TestModelStub::whereStartsWith('cn', 'J')
                    ->whereStartsWith('cn', 'Ja')
                    ->get();

        $this->assertCount(1, $models);
    }

    public function test_where_not_starts_with()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jane']]);
        TestModelStub::create(['cn' => ['Steve']]);

        $this->assertCount(1, TestModelStub::whereNotStartsWith('cn', 'J')->get());
        $this->assertCount(2, TestModelStub::whereNotStartsWith('cn', 'St')->get());
        $this->assertCount(3, TestModelStub::whereNotStartsWith('cn', 'teve')->get());
    }

    public function test_where_ends_with()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jen']]);
        TestModelStub::create(['cn' => ['Steve']]);

        $this->assertCount(2, TestModelStub::whereEndsWith('cn', 'n')->get());
        $this->assertCount(1, TestModelStub::whereEndsWith('cn', 'hn')->get());
        $this->assertCount(0, TestModelStub::whereEndsWith('cn', 'oh')->get());

        $models = TestModelStub::whereEndsWith('cn', 'n')
                    ->whereEndsWith('cn', 'hn')
                    ->get();

        $this->assertCount(1, $models);
    }

    public function test_where_not_ends_with()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jen']]);
        TestModelStub::create(['cn' => ['Steve']]);

        $this->assertCount(1, TestModelStub::whereNotEndsWith('cn', 'n')->get());
        $this->assertCount(2, TestModelStub::whereNotEndsWith('cn', 'hn')->get());
        $this->assertCount(3, TestModelStub::whereNotEndsWith('cn', 'oh')->get());
    }

    public function test_where_in()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jane']]);
        TestModelStub::create(['cn' => ['Steve']]);

        $this->assertCount(2, TestModelStub::whereIn('cn', ['John', 'Jane'])->get());
        $this->assertCount(3, TestModelStub::whereIn('cn', ['John', 'Jane', 'Steve'])->get());
        $this->assertCount(0, TestModelStub::whereIn('cn', ['Invalid'])->get());

        // Test query stacking.
        $stacked = TestModelStub::query()
            ->whereIn('cn', ['John', 'Jane'])
            ->whereStartsWith('cn', 'Jo')
            ->get();

        $this->assertCount(1, $stacked);
    }

    public function test_or_where()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['cn' => ['Jane']]);
        TestModelStub::create(['cn' => ['Jen']]);

        $results = TestModelStub::query()
            ->whereEquals('cn', 'John')
            ->orWhereEquals('cn', 'Jane')
            ->get();

        $this->assertCount(2, $results);
    }

    public function test_or_where_has()
    {
        TestModelStub::create(['cn' => ['John']]);
        TestModelStub::create(['sn' => ['Jane']]);
        TestModelStub::create(['name' => ['Jen']]);

        $results = TestModelStub::query()
            ->whereHas('cn')
            ->orWhereHas('sn')
            ->get();

        $this->assertCount(2, $results);
    }

    public function test_where_greater_than()
    {
        TestModelStub::create(['cn' => [0]]);
        $greater = TestModelStub::create(['cn' => [5]]);

        $results = TestModelStub::where('cn', '>=', 1)->get();
        $this->assertCount(1, $results);
        $this->assertTrue($greater->is($results->first()));
    }

    public function test_where_less_than()
    {
        $less = TestModelStub::create(['cn' => [0]]);
        TestModelStub::create(['cn' => [5]]);

        $results = TestModelStub::where('cn', '<=', 1)->get();
        $this->assertCount(1, $results);
        $this->assertTrue($less->is($results->first()));
    }

    public function test_where_between()
    {
        TestModelStub::create(['cn' => 1]);
        TestModelStub::create(['cn' => 2]);

        $this->assertCount(2, TestModelStub::whereBetween('cn', [1, 5])->get());
        $this->assertCount(0, TestModelStub::whereBetween('cn', [5, 10])->get());
    }

    public function test_creating_in()
    {
        $model = tap(new TestModelStub(['cn' => 'John']), function ($model) {
            $model->inside('ou=Users,dc=local,dc=com')->save();
        });

        $this->assertEquals($model->getDn(), 'cn=John,ou=Users,dc=local,dc=com');
    }

    public function test_querying_in()
    {
        TestModelStub::create(['cn' => 'John']);
        $model = tap(new TestModelStub(['cn' => 'Jane']), function ($model) {
            $model->inside('ou=Users,dc=local,dc=com')->save();
        });

        $results = TestModelStub::in('ou=Users,dc=local,dc=com')->get();
        $this->assertCount(1, $results);
        $this->assertTrue($model->is($results->first()));

        // Add another model inside a sub-scope.
        tap(new TestModelStub(['cn' => 'Jane']), function ($model) {
            $model->inside('ou=Parent,ou=Users,dc=local,dc=com')->save();
        });
        $this->assertCount(2, TestModelStub::in('ou=Users,dc=local,dc=com')->get());
    }

    public function test_listing()
    {
        TestModelStub::create(['cn' => 'John']);
        (new TestModelStub(['cn' => 'Jane']))->inside('ou=Users,dc=local,dc=com')->save();
        (new TestModelStub(['cn' => 'Jen']))->inside('ou=Managers,dc=local,dc=com')->save();
        (new TestModelStub(['cn' => 'Jen']))->inside('ou=Accounts,ou=Users,dc=local,dc=com')->save();

        $this->assertCount(2, TestModelStub::in('ou=Users,dc=local,dc=com')->get());
        $this->assertCount(1, TestModelStub::listing()->in('ou=Users,dc=local,dc=com')->get());
        $this->assertCount(4, TestModelStub::in('dc=local,dc=com')->get());
    }

    public function test_paginate()
    {
        TestModelStub::create(['cn' => 'John']);
        TestModelStub::create(['cn' => 'Jane']);

        $this->assertCount(2, TestModelStub::paginate());
    }

    public function test_has_many_relationship()
    {
        $group = Group::create(['cn' => 'Accounting']);
        $user = User::create(['cn' => 'John', 'memberof' => [$group->getDn()]]);

        $this->assertSame($group, $user->groups()->attach($group));
        $this->assertEquals($user->getDn(), $group->getFirstAttribute('member'));

        $this->assertTrue($user->is($group->members()->first()));
        $this->assertTrue($group->is($user->groups()->first()));

        $this->assertTrue($user->groups()->exists($group));
        $this->assertTrue($group->members()->exists($user));
    }

    public function test_has_one_relationship()
    {
        $manager = User::create(['cn' => 'John']);
        $user = User::create(['cn' => 'Jane']);

        $this->assertSame($manager, $user->manager()->attach($manager));
        $this->assertEquals($manager->getDn(), $user->getFirstAttribute('manager'));
        $this->assertTrue($manager->is($user->manager()->first()));
        $this->assertTrue($user->manager()->exists($manager));
    }

    public function test_has_many_in_relationship()
    {
        $member = TestHasManyInStub::create(['cn' => 'John']);
        $user = TestHasManyInStub::create(['cn' => 'Jane', 'members' => $member]);
        $this->assertTrue($member->is($user->members()->first()));
    }

    public function test_domain_scoping()
    {
        Container::addConnection(new Connection([
            'base_dn' => 'dc=alpha,dc=com',
        ]), 'alpha');

        Container::addConnection(new Connection([
            'base_dn' => 'dc=bravo,dc=com',
        ]), 'bravo');

        DirectoryEmulator::setup('alpha');
        DirectoryEmulator::setup('bravo');

        $alpha = new class extends Entry {
            protected $connection = 'alpha';
            public static $objectClasses = ['one', 'two'];
        };

        $bravo = new class extends Entry {
            protected $connection = 'bravo';
            public static $objectClasses = ['one', 'two'];
        };

        $alphaUser = $alpha::create(['cn' => 'John']);
        $bravoUser = $bravo::create(['cn' => 'Jane']);

        $this->assertTrue($alphaUser->is($alpha->first()));
        $this->assertTrue($bravoUser->is($bravo->first()));

        $this->assertFalse($alphaUser->is($bravo->first()));
        $this->assertFalse($bravoUser->is($alpha->first()));
    }
}

class TestModelStub extends Entry
{
    public static $objectClasses = ['one', 'two'];
}

class TestHasManyInStub extends TestModelStub
{
    public function members()
    {
        return $this->hasManyIn(TestModelStub::class, 'members');
    }
}
