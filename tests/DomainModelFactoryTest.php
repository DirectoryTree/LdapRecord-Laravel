<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainModelFactory;
use LdapRecord\Models\Model;

class DomainModelFactoryTest extends TestCase
{
    public function test_ldap_models_can_be_created()
    {
        $this->assertInstanceOf(Model::class, DomainModelFactory::createForDomain(new Domain('foo')));
    }
}
