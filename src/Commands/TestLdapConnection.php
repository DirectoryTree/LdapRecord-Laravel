<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\Command;
use LdapRecord\Auth\BindException;
use LdapRecord\Container;

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

            $start = microtime(true);

            try {
                $connection->connect();

                $message = 'Successfully connected.';
            } catch (BindException $e) {
                $detailedError = optional($e->getDetailedError());

                $errorCode = $detailedError->getErrorCode();
                $diagnosticMessage = $detailedError->getDiagnosticMessage();

                $message = "{$e->getMessage()}. ".
                    "Error Code: [$errorCode] ".
                    'Diagnostic Message: '.$diagnosticMessage ?? 'null';
            }

            $rows[] = [
                $name,
                $connection->isConnected() ? '✔ Yes' : '✘ No',
                $connection->getConfiguration()->get('username'),
                $message,
                $this->getElapsedTime($start).'ms',
            ];
        }

        $this->table(['Connection', 'Successful', 'Username', 'Message', 'Response Time'], $rows);
    }

    /**
     * Get the elapsed time since a given starting point.
     *
     * @param int $start
     *
     * @return float
     */
    protected function getElapsedTime($start)
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
}
