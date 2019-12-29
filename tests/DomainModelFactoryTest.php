<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Models\Model;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainModelFactory;

class DomainModelFactoryTest extends TestCase
{
    public function test_ldap_models_can_be_created()
    {
        $this->assertInstanceOf(Model::class, DomainModelFactory::createForDomain(new Domain('foo')));
    }
}
