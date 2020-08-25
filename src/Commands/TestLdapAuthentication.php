<?php

namespace LdapRecord\Laravel\Commands;

use LdapRecord\Models\Model;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\LdapUserRepository;

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
        $providers = ($provider = $this->argument('provider'))
            ? [$provider => config("auth.providers.$provider")]
            : config('auth.providers');

        foreach ($providers as $provider => $config) {
            if (! ($instance = Auth::getProvider($provider)) instanceof UserProvider) {
                $this->error("Authentication provider [$provider] does not use the LDAP authentication driver.");

                continue;
            }

            $this->info("Testing LDAP athentication provider [$provider]...");

            $tests = [];

            foreach ($this->runTests($instance) as $test => $result) {
                $tests[] = [
                    $provider,
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
        return [
            "Configured model is an LdapRecord model" => $this->testModelIsCorrectInstance($provider->getLdapUserRepository()),
            "Users are returned" => $this->testUsersAreReturned($provider->getLdapUserRepository()),
        ];
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
        if (! is_subclass_of($model = $repository->getModel(), $actual = Model::class)) {
            return "Your configured LdapRecord model [$model] does not extend [$actual].";
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
        if ($repository->query()->get()->isEmpty()) {
            $model = $repository->getModel();

            return "Your configured LdapRecord model [$model] returned no users.";
        }

        return true;
    }
}
