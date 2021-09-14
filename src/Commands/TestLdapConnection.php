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

        if (empty($connections)) {
            return $this->error('No LDAP connections have been defined.');
        }

        $tested = [];

        foreach ($connections as $name => $connection) {
            $tested[] = $this->performTest($name, $connection);
        }

        $this->table(['Connection', 'Successful', 'Username', 'Message', 'Response Time'], $tested);
    }

    /**
     * Perform a connectivity test on the given connection.
     *
     * @param string     $name
     * @param Connection $connection
     *
     * @return array
     */
    protected function performTest($name, Connection $connection)
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
     *
     * @param Connection $connection
     *
     * @return string
     */
    protected function attempt(Connection $connection)
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
                $errorCode,
                $diagnosticMessage ?? 'null'
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
