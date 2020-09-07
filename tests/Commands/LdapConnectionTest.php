<?php

namespace LdapRecord\Laravel\Tests\Commands;

use Mockery as m;
use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Configuration\DomainConfiguration;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class LdapConnectionTest extends TestCase
{
    public function test_command_tests_ldap_connectivity()
    {
        $connection = m::mock(Connection::class);

        Container::addConnection($connection);

        $connection->shouldReceive('connect')->once()->andReturnNull();
        $connection->shouldReceive('isConnected')->once()->andReturnTrue();
        $connection->shouldReceive('getConfiguration')->once()->andReturn(new DomainConfiguration(['username' => 'user']));

        $pendingCommand = $this->artisan('ldap:test', ['connection' => 'default']);

        $table = (new Table($output = new BufferedOutput))
            ->setHeaders(['Connection', 'Successful', 'Username', 'Message', 'Response Time'])
            ->setRows([
                [
                    'default', '✔ Yes', 'user', 'Successfully connected.', '0ms',
                ]
            ]);

        $table->render();

        $lines = array_filter(
            preg_split("/\n/", $output->fetch())
        );

        foreach ($lines as $line) {
            $pendingCommand->expectsOutput($line);
        }

        $pendingCommand->assertExitCode(0);
    }
}
