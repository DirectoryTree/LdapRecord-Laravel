<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Import\Hydrators\GuidHydrator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class GuidHydratorTest extends TestCase
{
    public function test_guid_hydrator()
    {
        $entry = new Entry(['objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2']);
        $model = new GuidHydratorTestModelStub;
        $hydrator = new GuidHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
    }
}

class GuidHydratorTestModelStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;
}
