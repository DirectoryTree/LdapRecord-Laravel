<?php

namespace LdapRecord\Laravel\Tests\Feature\Commands;

use LdapRecord\Auth\BindException;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\Laravel\Tests\TestCase;
use Mockery as m;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class TestLdapConnectionTest extends TestCase
{
    /**
     * @testWith
     * [{"connection": "default"}]
     * [[]]
     */
    public function test_command_tests_ldap_connectivity($params)
    {
        $connection = m::mock(Connection::class);

        $connection->shouldReceive('setDispatcher')->once()->with(DispatcherInterface::class)->andReturn($connection);

        Container::addConnection($connection);

        $connection->shouldReceive('connect')->once()->andReturnNull();
        $connection->shouldReceive('isConnected')->once()->andReturnTrue();
        $connection->shouldReceive('getConfiguration')->once()->andReturn(new DomainConfiguration(['username' => 'user']));

        $pendingCommand = $this->artisan('ldap:test', $params);

        $table = (new Table($output = new BufferedOutput))
            ->setHeaders(['Connection', 'Successful', 'Username', 'Message', 'Response Time'])
            ->setRows([
                ['default', '✔ Yes', 'user', 'Successfully connected.', '0ms'],
            ]);

        $table->render();

        $lines = array_filter(
            preg_split("/\n/", $output->fetch())
        );

        foreach ($lines as $line) {
            $pendingCommand->expectsOutput($line);
        }

        $pendingCommand->assertSuccessful();
    }

    /**
     * @testWith
     * [{"connection": "default"}]
     * [[]]
     */
    public function test_command_fails_when_connection_fails($params)
    {
        $connection = m::mock(Connection::class);

        $connection->shouldReceive('setDispatcher')->once()->with(DispatcherInterface::class)->andReturn($connection);

        Container::addConnection($connection);

        $connection->shouldReceive('connect')->once()->andThrow(new BindException('Unable to connect'));
        $connection->shouldReceive('isConnected')->once()->andReturnFalse();
        $connection->shouldReceive('getConfiguration')->once()->andReturn(new DomainConfiguration(['username' => 'user']));

        $pendingCommand = $this->artisan('ldap:test', $params);

        $table = (new Table($output = new BufferedOutput))
            ->setHeaders(['Connection', 'Successful', 'Username', 'Message', 'Response Time'])
            ->setRows([
                ['default', '✘ No', 'user', 'Unable to connect. Error Code: [NULL] Diagnostic Message: NULL', '0ms'],
            ]);

        $table->render();

        $lines = array_filter(
            preg_split("/\n/", $output->fetch())
        );

        foreach ($lines as $line) {
            $pendingCommand->expectsOutput($line);
        }

        $pendingCommand->assertFailed();
    }
}
