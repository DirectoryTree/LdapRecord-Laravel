<?php

namespace LdapRecord\Laravel\Commands;

use LdapRecord\Container;
use LdapRecord\Auth\BindException;
use Illuminate\Console\Command;

class TestLdapConnection extends Command
{
    /**
     * The signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:test {connection? : The name of the LDAP connection to test.}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Test the configured application LDAP connections.';

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws \LdapRecord\ContainerException
     */
    public function handle()
    {
        if ($connection = $this->argument('connection')) {
            $connections = [$connection => Container::getConnection($connection)];
        } else {
            $connections = Container::getInstance()->all();
        }

        $rows = [];

        foreach ($connections as $name => $connection) {
            $this->info("Testing LDAP connection [$name]...");

            try {
                $connection->connect();

                $message = "Successfully connected.";
            } catch (BindException $e) {
                $message = "Unable to connect. {$e->getMessage()}";
            }

            $rows[] = [$name, $connection->isConnected() ? '✔' : '✘', $message];
        }

        $this->table(['Connection', 'Successful', 'Message'], $rows);
    }
}
