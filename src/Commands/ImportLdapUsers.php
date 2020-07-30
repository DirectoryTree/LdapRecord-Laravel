<?php

namespace LdapRecord\Laravel\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\DetectsSoftDeletes;
use LdapRecord\Laravel\LdapImporter;
use LdapRecord\Models\Model as LdapModel;
use LdapRecord\Models\Types\ActiveDirectory;

class ImportLdapUsers extends Command
{
    use DetectsSoftDeletes;

    /**
     * The signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:import {provider : The authentication provider to import.}
            {user? : The specific user to import.}
            {--f|filter= : The raw LDAP filter for limiting users imported.}
            {--a|attributes= : Comma separated list of LDAP attributes to select. }
            {--d|delete : Soft-delete the users model if their LDAP account is disabled.}
            {--r|restore : Restores soft-deleted models if their LDAP account is enabled.}
            {--delete-missing : Soft-delete all users that are missing from the import. }
            {--no-log : Disables logging successful and unsuccessful imports.}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Imports LDAP users into the application database.';

    /**
     * A list of user GUIDs that were successfully imported.
     *
     * @var array
     */
    protected $imported = [];

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public function handle()
    {
        /** @var \LdapRecord\Laravel\Auth\DatabaseUserProvider $provider */
        $provider = Auth::createUserProvider($this->argument('provider'));

        if (! $provider instanceof UserProvider) {
            return $this->error("Provider [{$this->argument('provider')}] is not configured for LDAP authentication.");
        } elseif (! $provider instanceof DatabaseUserProvider) {
            return $this->error("Provider [{$this->argument('provider')}] is not configured for database synchronization.");
        }

        $import = $this->newLdapUserImport()
                ->setLdapImporter($provider->getLdapUserImporter())
                ->setLdapUserRepository($provider->getLdapUserRepository());

        $import->registerEventCallback('deleting-missing', function () {
            $this->info('Soft-deleting all missing users...');
        });

        $import->registerEventCallback('deleted-missing', function ($database, $ldap, $ids) {
            $this->info("Successfully soft-deleted [{$ids->count()}] users.");
        });

        $users = $import->loadObjectsFromRepository($this->argument('user'));

        if (($count = $users->count()) === 0) {
            return $this->info('There were no users found to import.');
        } elseif ($count === 1) {
            $this->info("Found user [{$users->first()->getRdn()}].");
        } else {
            $this->info("Found [$count] user(s).");
        }

        if (
            $this->input->isInteractive()
            && $this->confirm('Would you like to display the user(s) to be imported / synchronized?', $default = false)
        ) {
            $this->display($users);
        }

        if (
            ! $this->input->isInteractive()
            || $this->confirm('Would you like these users to be imported / synchronized?', $default = true)
        ) {
            $imported = $import->execute();

            $this->info("Successfully imported / synchronized [{$imported->count()}] user(s).");
        } else {
            $this->info('Okay, no users were imported / synchronized.');
        }
    }

    /**
     * Create a new LDAP user import.
     *
     * @return LdapUserImport
     */
    protected function newLdapUserImport()
    {
        $import = new LdapUserImport();

        if ($filter = $this->option('filter')) {
            $import->applyFilter($filter);
        }

        if ($attributes = $this->option('attributes')) {
            $import->limitAttributes(explode(',', $attributes));
        }

        if ($this->isRestoring()) {
            $import->restoreEnabledUsers();
        }

        if ($this->isDeleting()) {
            $import->trashDisabledUsers();
        }

        if ($this->isDeletingMissing()) {
            $import->trashMissing();
        }

        return $import;
    }

    /**
     * Displays the given users in a table.
     *
     * @param \LdapRecord\Query\Collection $users
     *
     * @return void
     */
    public function display($users = [])
    {
        $headers = ['Name', 'Distinguished Name'];

        $data = [];

        foreach ($users as $user) {
            $data[] = [
                'name' => $user->getRdn(),
                'dn' => $user->getDn(),
            ];
        }

        $this->table($headers, $data);
    }

    /**
     * Import the users and return the total number imported.
     *
     * @param LdapImporter $importer
     * @param array        $users
     *
     * @return void
     */
    public function import(LdapImporter $importer, array $users = [])
    {
        $this->imported = [];

        $this->output->progressStart(count($users));

        /** @var LdapModel $user */
        foreach ($users as $user) {
            try {
                // Import the user and retrieve it's model.
                $model = $importer->run($user);

                // Save the returned model.
                $this->save($user, $model);

                if ($user instanceof ActiveDirectory) {
                    if ($this->isDeleting()) {
                        $this->delete($user, $model);
                    }

                    if ($this->isRestoring()) {
                        $this->restore($user, $model);
                    }
                }

                $this->imported[] = $user->getConvertedGuid();
            } catch (Exception $e) {
                // Log the unsuccessful import.
                if ($this->isLogging()) {
                    logger()->error("Importing user [{$user->getRdn()}] failed. {$e->getMessage()}");
                }
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();
    }

    /**
     * Determine if logging is enabled.
     *
     * @return bool
     */
    public function isLogging()
    {
        return ! $this->option('no-log');
    }

    /**
     * Determine if soft-deleting disabled user accounts is enabled.
     *
     * @return bool
     */
    public function isDeleting()
    {
        return $this->option('delete') == 'true';
    }

    /**
     * Determine if soft-deleting all missing users is enabled.
     *
     * @return bool
     */
    public function isDeletingMissing()
    {
        return $this->option('delete-missing') == 'true' && is_null($this->argument('user'));
    }

    /**
     * Determine if restoring re-enabled users is enabled.
     *
     * @return bool
     */
    public function isRestoring()
    {
        return $this->option('restore') == 'true';
    }
}
