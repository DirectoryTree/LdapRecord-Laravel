<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainRegistrar;
use LdapRecord\Laravel\RegistrarException;
use Mockery as m;

class DomainRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Flush the container instance.
        Container::getNewInstance();
    }

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

    public function test_domains_can_be_setup_and_added_to_the_container()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldNotReceive('connect');

        $domain = m::mock(Domain::class);
        $domain->shouldReceive('getName')->once()->andReturn('test');
        $domain->shouldReceive('getNewConnection')->once()->andReturn($connection);
        $domain->shouldReceive('shouldAutoConnect')->once()->andReturnFalse();
        $domain->shouldReceive('setConnection')->once()->withArgs([$connection]);

        $registrar = new DomainRegistrar();
        $registrar->add($domain);
        $registrar->setup();

        $container = Container::getInstance();
        $this->assertTrue($container->exists('test'));
        $this->assertInstanceOf(Connection::class, $container->get('test'));
    }

    public function test_domain_connections_are_replaced_in_the_container_when_calling_setup_multiple_times()
    {
        $connection = m::mock(Connection::class);
        $connection->shouldNotReceive('connect');

        $domain = m::mock(Domain::class);
        $domain->shouldReceive('getName')->once()->andReturn('test');
        $domain->shouldReceive('getNewConnection')->once()->andReturn($connection);
        $domain->shouldReceive('shouldAutoConnect')->once()->andReturnFalse();
        $domain->shouldReceive('setConnection')->once()->withArgs([$connection]);

        $registrar = new DomainRegistrar();
        $registrar->add($domain);
        $registrar->setup();

        $container = Container::getInstance();
        $this->assertTrue($container->exists('test'));
        $this->assertInstanceOf(Connection::class, $container->get('test'));
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
