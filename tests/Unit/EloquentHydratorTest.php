<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Import\EloquentHydrator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class EloquentHydratorTest extends TestCase
{
    public function test_hydrator_uses_all_hydrators()
    {
        $entry = new Entry([
            'bar' => 'baz',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $model = new EloquentHydratorTestModelStub;

        (new EloquentHydrator(['sync_attributes' => ['foo' => 'bar']]))
            ->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
        $this->assertEquals('default', $model->domain);
        $this->assertEquals($entry->getConvertedGuid(), $model->guid);
    }
}

class EloquentHydratorTestModelStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;
}
