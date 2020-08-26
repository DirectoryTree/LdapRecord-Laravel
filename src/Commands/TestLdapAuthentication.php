<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use LdapRecord\LdapRecordException;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Model as LdapRecord;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use Illuminate\Database\Eloquent\Model as Eloquent;

class TestLdapAuthentication extends Command
{
    /**
     * The signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:test-auth {provider? : The name of the LDAP provider to test.}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Test the configured application LDAP authentication providers.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $providers = ($providerName = $this->argument('provider'))
            ? [$providerName => config("auth.providers.$providerName")]
            : config('auth.providers');

        foreach ($providers as $name => $config) {
            $provider = Auth::createUserProvider($name);

            if (! $provider instanceof UserProvider) {
                $this->info("Authentication provider [$name] does not use the LDAP authentication driver. Skipping...");

                continue;
            }

            $this->info("Testing LDAP athentication provider [$name]...");

            $tests = [];

            foreach ($this->runTests($provider) as $test => $result) {
                $tests[] = [
                    $name,
                    $test,
                    is_bool($result) ? '✔ Yes' : '✘ No',
                    is_bool($result) ? '' : $result,
                ];
            }

            $this->table(['Auth Provider', 'Test Executed', 'Successful', 'Message'], $tests);
        }
    }

    /**
     * Run the authentication provider tests.
     *
     * @param UserProvider $provider
     *
     * @return array
     */
    protected function runTests(UserProvider $provider)
    {
        $tests = [
            'Configured model is an LdapRecord model' => $this->testModelIsCorrectInstance($provider->getLdapUserRepository()),
            'Can connect to LDAP server' => $this->testConnectivity($provider->getLdapUserRepository()),
        ];

        // If the LDAP server connectivity test passes, we will
        // append the tests that require connectivity to run,
        // since they would of course fail otherwise.
        if ($tests['Can connect to LDAP server'] === true) {
            $tests += ['Users are discoverable' => $this->testUsersAreReturned($provider->getLdapUserRepository())];
        }

        if ($provider instanceof DatabaseUserProvider) {
            $tests += [
                'Configured database model is an Eloquent model' => $this->testConfiguredDatabaseModelIsEloquent($provider->getLdapUserImporter())
            ];
        }

        return $tests;
    }

    /**
     * Ensure that LdapRecord can connect to the LDAP server.
     *
     * @param LdapUserRepository $repository
     *
     * @return bool|string
     */
    protected function testConnectivity(LdapUserRepository $repository)
    {
        $connection = $repository->query()->getConnection();

        try {
            $connection->reconnect();

            return true;
        } catch (LdapRecordException $ex) {
            return $ex->getMessage();
        }
    }

    /**
     * Ensure that the LdapRecord model configured is in-fact an LdapRecord model.
     *
     * @param LdapUserRepository $repository
     *
     * @return bool|string
     */
    protected function testModelIsCorrectInstance(LdapUserRepository $repository)
    {
        if (! is_subclass_of($model = $repository->getModel(), $required = LdapRecord::class)) {
            return "Configured LdapRecord model [$model] does not extend [$required].";
        }

        return true;
    }

    /**
     * Ensure that users are returned from the configured LdapRecord model.
     *
     * @param LdapUserRepository $repository
     *
     * @return bool|string
     */
    protected function testUsersAreReturned(LdapUserRepository $repository)
    {
        if ($repository->query()->get()->isNotEmpty()) {
            return true;
        }

        $model = $repository->getModel();

        $query = $repository->query();

        if ($query->withoutGlobalScopes()->get()->isNotEmpty()) {
            return "Configured LdapRecord model [$model] has global scopes preventing users from being returned.";
        }

        return "Configured LdapRecord model [$model] returned no users.";
    }

    /**
     * Ensure that the configured database model is in-fact an Eloquent model.
     *
     * @param LdapUserImporter $importer
     *
     * @return bool|string
     */
    protected function testConfiguredDatabaseModelIsEloquent(LdapUserImporter $importer)
    {
        if (! is_subclass_of($model = $importer->getEloquentModel(), $eloquent = Eloquent::class)) {
            return "Configured database model [$model] does not extend [$eloquent]";
        }

        return true;
    }
}
