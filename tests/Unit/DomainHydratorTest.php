<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Import\Hydrators\DomainHydrator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class DomainHydratorTest extends TestCase
{
    public function test_domain_hydrator_uses_default_connection_name()
    {
        $entry = new Entry;
        $model = new DomainHydratorTestModelStub;
        $hydrator = new DomainHydrator;

        $hydrator->hydrate($entry, $model);

        $this->assertEquals('default', $model->domain);
    }
}

class DomainHydratorTestModelStub extends Model implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;
}
