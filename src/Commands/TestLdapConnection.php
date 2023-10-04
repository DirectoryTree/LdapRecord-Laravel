<?php

namespace LdapRecord\Laravel\Commands;

use Exception;
use Illuminate\Console\Command;
use LdapRecord\Auth\BindException;
use LdapRecord\Connection;
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
     * @throws \LdapRecord\ContainerException
     */
    public function handle(): int
    {
        if ($connection = $this->argument('connection')) {
            $connections = [$connection => Container::getConnection($connection)];
        } else {
            $connections = Container::getInstance()->getConnections();
        }

        if (empty($connections)) {
            $this->error('No LDAP connections have been defined.');

            return static::INVALID;
        }

        $tested = [];
        $connected = [];

        foreach ($connections as $name => $connection) {
            [,$connected[]] = $tested[] = $this->performTest($name, $connection);
        }

        $this->table(['Connection', 'Successful', 'Username', 'Message', 'Response Time'], $tested);

        return in_array('✘ No', $connected) ? static::FAILURE : static::SUCCESS;
    }

    /**
     * Perform a connectivity test on the given connection.
     */
    protected function performTest(string $name, Connection $connection): array
    {
        $this->info("Testing LDAP connection [$name]...");

        $start = microtime(true);

        $message = $this->attempt($connection);

        return [
            $name,
            $connection->isConnected() ? '✔ Yes' : '✘ No',
            $connection->getConfiguration()->get('username'),
            $message,
            (app()->runningUnitTests() ? '0' : $this->getElapsedTime($start)).'ms',
        ];
    }

    /**
     * Attempt establishing the connection.
     */
    protected function attempt(Connection $connection): string
    {
        try {
            $connection->connect();

            $message = 'Successfully connected.';
        } catch (BindException $e) {
            $detailedError = optional($e->getDetailedError());

            $errorCode = $detailedError->getErrorCode();
            $diagnosticMessage = $detailedError->getDiagnosticMessage();

            $message = sprintf(
                '%s. Error Code: [%s] Diagnostic Message: %s',
                $e->getMessage(),
                $errorCode ?? 'NULL',
                $diagnosticMessage ?? 'NULL'
            );
        } catch (Exception $e) {
            $message = sprintf(
                '%s. Error Code: [%s]',
                $e->getMessage(),
                $e->getCode()
            );
        }

        return $message;
    }

    /**
     * Get the elapsed time since a given starting point.
     */
    protected function getElapsedTime(int $start): int|float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }
}
