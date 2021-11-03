<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use LdapRecord\Container;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\TestCase;
use Ramsey\Uuid\Uuid;

class EmulatedQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        DirectoryEmulator::setup('default');
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

    public function test_update_attributes()
    {
        $query = Container::getConnection('default')->query();

        $dn = 'cn=John Doe,dc=local,dc=com';
        $attributes = ['objectclass' => ['foo', 'bar']];

        $this->assertTrue($query->insert($dn, $attributes));

        $result = $query->first();
        $this->assertEquals($attributes['objectclass'], $result['objectclass']);

        $newAttributes = ['objectclass' => ['baz', 'zal'], 'cn' => ['John Doe']];

        $query->updateAttributes($dn, $newAttributes);

        $result = $query->newInstance()->first();
        $this->assertEquals($newAttributes['cn'], $result['cn']);
        $this->assertEquals($newAttributes['objectclass'], $result['objectclass']);
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

    public function test_find_is_case_insensitive()
    {
        $query = Container::getConnection('default')->query();

        $this->assertNull($query->find('foo'));

        $dn = 'cn=John Doe,dc=local,dc=com';
        $attributes = ['objectclass' => ['foo', 'bar']];

        $query->insert($dn, $attributes);

        $record = $query->find('cn=john doe,dc=local,dc=com');

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

    public function test_guid_key_is_determined_from_attributes()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz'], 'guid' => [$guid = Uuid::uuid4()->toString()]];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', $attributes);

        $record = $query->first();

        $this->assertArrayHasKey('guid', $record);
        $this->assertEquals($guid, $record['guid'][0]);
    }

    public function test_select()
    {
        $query = Container::getConnection('default')->query();

        $attributes = [
            'sn' => ['Doe'],
            'givenname' => ['John'],
            'samaccountname' => ['jdoe'],
            'objectclass' => ['bar', 'baz'],
        ];

        $query->insert('cn=John Doe,dc=local,dc=com', $attributes);

        $record = $query->select(['givenname'])->first();

        $this->assertArrayHasKey('dn', $record);
        $this->assertArrayHasKey('givenname', $record);

        $this->assertEquals(['John'], $record['givenname']);
        $this->assertEquals(['cn=John Doe,dc=local,dc=com'], $record['dn']);
    }

    public function test_where()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['samaccountname' => ['johndoe']]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['samaccountname' => ['janedoe']]));

        $results = $query->where('samaccountname', '=', 'johndoe')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($john, $results[0]['dn'][0]);
        $this->assertEquals('johndoe', $results[0]['samaccountname'][0]);
    }

    public function test_where_values_are_case_insensitive()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['samaccountname' => ['johndoe']]));

        $results = $query->where('samaccountname', '=', 'JOHNdoe')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($john, $results[0]['dn'][0]);
        $this->assertEquals('johndoe', $results[0]['samaccountname'][0]);
    }

    public function test_where_returns_same_results_with_alternate_attribute_casing()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['sAMAccountName' => ['johndoe']]));

        $result = $query->where('samaccountname', '=', 'johndoe')->first();
        $this->assertEquals('johndoe', $result['samaccountname'][0]);

        $this->assertTrue($query->updateAttributes($john, ['sAMAccountName' => 'jdoe']));

        $result = $query->newInstance()->where('samaccountname', '=', 'jdoe')->first();

        $this->assertEquals('jdoe', $result['samaccountname'][0]);
    }

    public function test_where_has()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['foo' => ['bar']]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['bar' => ['baz']]));

        $results = $query->whereHas('bar')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($jane, $results[0]['dn'][0]);
        $this->assertEquals('baz', $results[0]['bar'][0]);
    }

    public function test_where_has_multiple()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['foo' => ['bar'], 'baz' => ['zal']]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['bar' => ['baz']]));

        $results = $query->whereHas('foo')->whereHas('baz')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($john, $results[0]['dn'][0]);
        $this->assertEquals('bar', $results[0]['foo'][0]);
        $this->assertEquals('zal', $results[0]['baz'][0]);
    }

    public function test_where_not_has()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['foo' => ['bar'], 'baz' => ['zal']]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['bar' => ['baz']]));

        $results = $query->whereNotHas('foo')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($jane, $results[0]['dn'][0]);
        $this->assertEquals('baz', $results[0]['bar'][0]);
    }

    public function test_where_contains()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['foo' => ['bar'], 'baz' => ['zal']]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['bar' => ['baz']]));

        $results = $query->whereContains('baz', 'zal')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($john, $results[0]['dn'][0]);
        $this->assertEquals('zal', $results[0]['baz'][0]);
    }

    public function test_where_not_contains()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jane']]));

        $results = $query->whereNotContains('cn', 'Jane')->get();

        $this->assertCount(1, $results);
        $this->assertEquals($john, $results[0]['dn'][0]);
        $this->assertEquals('John', $results[0]['cn'][0]);
    }

    public function test_where_starts_with()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jane']]));

        $this->assertCount(2, $query->get());
        $this->assertCount(2, $query->newInstance()->whereStartsWith('cn', 'J')->get());
        $this->assertCount(1, $query->newInstance()->whereStartsWith('cn', 'John')->get());
        $this->assertCount(0, $query->newInstance()->whereStartsWith('cn', 'JohnJane')->get());
    }

    public function test_where_not_starts_with()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jane']]));

        $this->assertCount(2, $query->get());
        $this->assertCount(1, $query->newInstance()->whereNotStartsWith('cn', 'John')->get());
        $this->assertCount(0, $query->newInstance()->whereNotStartsWith('cn', 'J')->get());
    }

    public function test_where_ends_with()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jane']]));

        $this->assertCount(2, $query->get());
        $this->assertCount(1, $query->newInstance()->whereEndsWith('cn', 'hn')->get());
        $this->assertCount(1, $query->newInstance()->whereEndsWith('cn', 'ane')->get());
        $this->assertCount(0, $query->newInstance()->whereEndsWith('cn', 'Johnn')->get());
    }

    public function test_where_not_ends_with()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jane']]));
        $query->insert('cn=Steve,dc=local,dc=com', array_merge($attributes, ['cn' => ['Steve']]));

        $this->assertCount(3, $query->get());
        $this->assertCount(2, $query->newInstance()->whereNotEndsWith('cn', 'hn')->get());
        $this->assertCount(1, $query->newInstance()->whereNotEndsWith('cn', 'e')->get());
        $this->assertCount(3, $query->newInstance()->whereNotEndsWith('cn', 'invalid')->get());
    }

    public function test_where_in()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jane']]));
        $query->insert('cn=Steve,dc=local,dc=com', array_merge($attributes, ['cn' => ['Steve']]));

        $this->assertCount(3, $query->get());
        $this->assertCount(2, $query->newInstance()->whereIn('cn', ['John', 'Jane'])->get());
        $this->assertCount(3, $query->newInstance()->whereIn('cn', ['John', 'Jane', 'Steve'])->get());
        $this->assertCount(0, $query->newInstance()->whereIn('cn', ['Invalid'])->get());
    }

    public function test_or_where()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jane']]));
        $query->insert('cn=Jen,dc=local,dc=com', array_merge($attributes, ['cn' => ['Jen']]));

        $results = $query
            ->whereEquals('cn', 'John')
            ->orWhereEquals('cn', 'Jane')
            ->get();

        $this->assertCount(2, $results);
    }

    public function test_or_where_has()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['sn' => ['Jane']]));
        $query->insert('cn=Jen,dc=local,dc=com', array_merge($attributes, ['name' => ['Jen']]));

        $results = $query
            ->whereHas('cn')
            ->orWhereHas('sn')
            ->get();

        $this->assertCount(2, $results);
    }

    public function test_where_greater_than()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => [2]]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => [4]]));

        $results = $query->where('cn', '>=', 3)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($jane, $results[0]['dn'][0]);
    }

    public function test_where_less_than()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => [2]]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => [4]]));

        $results = $query->where('cn', '<=', 3)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($john, $results[0]['dn'][0]);
    }

    public function test_where_between()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert($john = 'cn=John Doe,dc=local,dc=com', array_merge($attributes, ['cn' => [2]]));
        $query->insert($jane = 'cn=Jane Doe,dc=local,dc=com', array_merge($attributes, ['cn' => [4]]));

        $this->assertCount(2, $query->newInstance()->whereBetween('cn', [0, 5])->get());
        $this->assertCount(1, $query->newInstance()->whereBetween('cn', [3, 5])->get());
    }

    public function test_in()
    {
        $query = Container::getConnection('default')->query();

        $attributes = ['objectclass' => ['bar', 'baz']];

        $query->insert('cn=John Doe,ou=Users,dc=local,dc=com', array_merge($attributes, ['cn' => ['John']]));
        $query->insert('cn=Jane Doe,ou=Users,dc=local,dc=com', array_merge($attributes, ['sn' => ['Jane']]));
        $query->insert('cn=Jen,ou=Other,dc=local,dc=com', array_merge($attributes, ['name' => ['Jen']]));

        $this->assertCount(2, $query->newInstance()->in('ou=Users,dc=local,dc=com')->get());
        $this->assertCount(1, $query->newInstance()->in('ou=Other,dc=local,dc=com')->get());
    }
}
