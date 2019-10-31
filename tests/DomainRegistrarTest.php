<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainRegistrar;
use LdapRecord\Laravel\RegistrarException;

class DomainRegistrarTest extends TestCase
{
    public function test_domains_can_be_added()
    {
        $domains = [new TestDomain('test')];
        $registrar = new DomainRegistrar($domains);

        $this->assertCount(1, $registrar->get());
        $this->assertTrue($registrar->exists('test'));
        $this->assertFalse($registrar->exists('invalid'));
        $this->assertInstanceOf(TestDomain::class, $registrar->get('test'));

        $registrar->add(new TestDomain('other'));
    }

    public function test_domains_can_be_removed()
    {
        $domains = [new TestDomain('test')];
        $registrar = new DomainRegistrar($domains);

        $this->assertInstanceOf(TestDomain::class, $registrar->get('test'));

        $registrar->remove('test');

        $this->expectException(RegistrarException::class);

        $registrar->get('test');
    }

    public function test_domains_can_be_set()
    {
        $domains = [new TestDomain('test'), new TestDomain('other')];
        $registrar = new DomainRegistrar($domains);

        $this->assertInstanceOf(TestDomain::class, $registrar->get('test'));
        $this->assertInstanceOf(TestDomain::class, $registrar->get('other'));
        $this->assertCount(2, $registrar->get());
    }

    public function test_exception_is_thrown_when_domain_does_not_exist()
    {
        $this->expectException(RegistrarException::class);

        (new DomainRegistrar())->get('invalid');
    }
}

class TestDomain extends Domain
{
    /**
     * Get the configuration of the domain.
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [];
    }
}
