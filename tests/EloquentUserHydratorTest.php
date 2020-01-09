<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Models\Entry;
use LdapRecord\Laravel\EloquentUserHydrator;
use LdapRecord\Laravel\Hydrators\GuidHydrator;
use LdapRecord\Laravel\Hydrators\DomainHydrator;
use LdapRecord\Laravel\Hydrators\AttributeHydrator;

class EloquentUserHydratorTest extends TestCase
{
    public function test_guid_hydrator()
    {
        $entry = new Entry(['objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2']);

        $model = new TestUser;
        $hydrator = new GuidHydrator();

        $hydrator->hydrate($entry, $model);

        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
    }

    public function test_domain_hydrator()
    {
        $entry = new Entry;
        $model = new TestUser;
        $hydrator = new DomainHydrator();

        $hydrator->hydrate($entry, $model);

        $this->assertEquals($entry->getConnectionName(), $model->domain);
    }

    public function test_attribute_hydrator()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new TestUser;
        $hydrator = new AttributeHydrator(['sync_attributes' => ['foo' => 'bar']]);

        $hydrator->hydrate($entry, $model);
        $this->assertEquals('baz', $model->foo);
    }

    public function test_hydrator_uses_all_hydrators()
    {
        $entry = new Entry([
            'bar' => 'baz',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2'
        ]);
        $model = new TestUser;

        EloquentUserHydrator::hydrate($entry, $model, ['sync_attributes' => ['foo' => 'bar']]);

        $this->assertEquals('baz', $model->foo);
        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
        $this->assertEquals($entry->getConnectionName(), $model->domain);
    }
}
