<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Log;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainRegistrar;
use LdapRecord\LdapRecordException;
use Mockery as m;

class DomainRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Flush registered domains.
        DomainRegistrar::register([]);

        // Flush the container instance.
        Container::getNewInstance();
    }

    public function test_domains_can_be_added()
    {
        DomainRegistrar::register(Domain::class);

        $this->assertCount(1, DomainRegistrar::$domains);
        $this->assertEquals(Domain::class, DomainRegistrar::$domains[0]);
    }

    public function test_setup_does_nothing_when_no_domains_registered()
    {
        DomainRegistrar::setup();

        $this->assertEmpty(Container::getInstance()->all());
    }

    public function test_domain_connections_are_added_into_the_container_when_setup()
    {
        DomainRegistrar::register(Domain::class);
        DomainRegistrar::setup();
        $this->assertInstanceOf(Connection::class, Container::getConnection(Domain::class));
    }

    public function test_domain_connections_are_attempted_when_auto_connect_is_enabled()
    {
        DomainRegistrar::register(TestDomainWithSuccessfulConnectionStub::class);
        DomainRegistrar::setup();
        $this->assertInstanceOf(Connection::class, Container::getConnection(TestDomainWithSuccessfulConnectionStub::class));
    }

    public function test_failed_domain_connections_are_logged_when_logging_is_enabled()
    {
        DomainRegistrar::logging(true);
        DomainRegistrar::register(TestDomainWithFailingConnectionStub::class);

        Log::shouldReceive('error')->once()->withArgs(['Failed connecting']);

        DomainRegistrar::setup();
    }
}

class TestDomainWithSuccessfulConnectionStub extends Domain
{
    public static $shouldAutoConnect = true;

    public static function getNewConnection()
    {
        $conn = m::mock(Connection::class);
        $conn->shouldReceive('isConnected')->once()->withNoArgs()->andReturnFalse();
        $conn->shouldReceive('connect')->once()->withNoArgs();
        return $conn;
    }
}

class TestDomainWithFailingConnectionStub extends Domain
{
    public static $shouldAutoConnect = true;

    public static function getNewConnection()
    {
        $conn = m::mock(Connection::class);
        $conn->shouldReceive('isConnected')->once()->withNoArgs()->andReturnFalse();
        $conn->shouldReceive('connect')->once()->andThrow(new LdapRecordException('Failed connecting'));
        return $conn;
    }
}
