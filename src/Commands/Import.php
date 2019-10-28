<?php

namespace LdapRecord\Laravel\Commands;

use Exception;
use RuntimeException;
use LdapRecord\Laravel\Domain;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\DomainRegistrar;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Models\Model as LdapModel;

class Import extends Command
{
    /**
     * The user model to use for importing.
     *
     * @var string
     */
    protected $model;

    /**
     * The signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:import {domain : The name of the domain to import.}
            {user? : The specific user to import.}
            {--f|filter= : The raw LDAP filter for limiting users imported.}
            {--m|model= : The model to use for importing users.}
            {--d|delete : Soft-delete the users model if their LDAP account is disabled.}
            {--r|restore : Restores soft-deleted models if their LDAP account is enabled.}
            {--no-log : Disables logging successful and unsuccessful imports.}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Imports LDAP users into the local database with a random 16 character hashed password.';

    /**
     * Execute the console command.
     *
     * @param DomainRegistrar $registrar
     *
     * @return void
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public function handle(DomainRegistrar $registrar)
    {
        $domainName = $this->argument('domain');

        $domain = $registrar->get($domainName);

        if (! $domain->isUsingDatabase()) {
            throw new RuntimeException("Domain '$domainName' is not configured for importing.");
        }

        $users = $this->getUsers($domain);

        $count = count($users);

        if ($count === 0) {
            throw new RuntimeException('There were no users found to import.');
        } elseif ($count === 1) {
            $this->info("Found user '{$users[0]->getRdn()}'.");
        } else {
            $this->info("Found {$count} user(s).");
        }

        if (
            $this->input->isInteractive() &&
            $this->confirm('Would you like to display the user(s) to be imported / synchronized?', $default = false)
        ) {
            $this->display($users);
        }

        if (
            ! $this->input->isInteractive() ||
            $this->confirm('Would you like these users to be imported / synchronized?', $default = true)
        ) {
            $imported = $this->import($domain, $users);

            $this->info("Successfully imported / synchronized {$imported} user(s).");
        } else {
            $this->info('Okay, no users were imported / synchronized.');
        }
    }

    /**
     * Imports the specified users and returns the total
     * number of users successfully imported.
     *
     * @param Domain $domain
     * @param array  $users
     *
     * @return int
     */
    public function import(Domain $domain, array $users = []) : int
    {
        $imported = 0;

        $this->output->progressStart(count($users));

        $databaseModel = $domain->getDatabaseModel();

        /** @var LdapModel $user */
        foreach ($users as $user) {
            try {
                // Import the user and retrieve it's model.
                $model = $domain->importer()->run(new $databaseModel);

                // Set the users password.
                $domain->passwordSynchronizer()->run($model);

                // Save the returned model.
                $this->save($user, $model);

                if ($this->isDeleting()) {
                    $this->delete($user, $model);
                }

                if ($this->isRestoring()) {
                    $this->restore($user, $model);
                }

                $imported++;
            } catch (Exception $e) {
                // Log the unsuccessful import.
                if ($this->isLogging()) {
                    logger()->error("Unable to import user {$user->getCommonName()}. {$e->getMessage()}");
                }
            }

            $this->output->progressAdvance();
        }

        $this->output->progressFinish();

        return $imported;
    }

    /**
     * Displays the given users in a table.
     *
     * @param array $users
     *
     * @return void
     */
    public function display(array $users = [])
    {
        $headers = ['Name', 'Distinguished Name'];

        $data = [];

        array_map(function (LdapModel $user) use (&$data) {
            $data[] = [
                'name' => $user->getRdn(),
                'dn' => $user->getDn(),
            ];
        }, $users);

        $this->table($headers, $data);
    }

    /**
     * Returns true / false if the current import is being logged.
     *
     * @return bool
     */
    public function isLogging() : bool
    {
        return ! $this->option('no-log');
    }

    /**
     * Returns true / false if users are being deleted
     * if their account is disabled in LDAP.
     *
     * @return bool
     */
    public function isDeleting() : bool
    {
        return $this->option('delete') == 'true';
    }

    /**
     * Returns true / false if users are being restored
     * if their account is enabled in LDAP.
     *
     * @return bool
     */
    public function isRestoring() : bool
    {
        return $this->option('restore') == 'true';
    }

    /**
     * Retrieves users to be imported.
     *
     * @param Domain $domain
     *
     * @return \LdapRecord\Models\Model[]
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public function getUsers(Domain $domain) : array
    {
        $query = $domain->locate()->query();

        if ($filter = $this->option('filter')) {
            // If the filter option was given, we'll
            // insert it into our search query.
            $query->rawFilter($filter);
        }

        if ($user = $this->argument('user')) {
            return [$query->findByAnrOrFail($user)];
        }

        // Retrieve all users. We'll paginate our search in case we
        // hit the 1000 record hard limit of active directory.
        return $query->paginate()->toArray();
    }

    /**
     * Saves the specified user with its model.
     *
     * @param LdapModel $user
     * @param Model     $model
     *
     * @return bool
     */
    protected function save(LdapModel $user, Model $model) : bool
    {
        if ($model->save() && $model->wasRecentlyCreated) {
            event(new Imported($user, $model));

            // Log the successful import.
            if ($this->isLogging()) {
                logger()->info("Imported user {$user->getRdn()}");
            }

            return true;
        }

        return false;
    }

    /**
     * Restores soft-deleted models if their LDAP account is enabled.
     *
     * @param LdapModel $user
     * @param Model     $model
     *
     * @return void
     */
    protected function restore(LdapModel $user, Model $model)
    {
        if (
            $this->isUsingSoftDeletes($model) &&
            $model->trashed() &&
            $user->isEnabled()
        ) {
            // If the model has soft-deletes enabled, the model is
            // currently deleted, and the LDAP user account
            // is enabled, we'll restore the users model.
            $model->restore();

            if ($this->isLogging()) {
                logger()->info("Restored user {$user->getRdn()}. Their user account has been re-enabled.");
            }
        }
    }

    /**
     * Soft deletes the specified model if their LDAP account is disabled.
     *
     * @param LdapModel $user
     * @param Model     $model
     *
     * @throws Exception
     *
     * @return void
     */
    protected function delete(LdapModel $user, Model $model)
    {
        if (
            $this->isUsingSoftDeletes($model) &&
            ! $model->trashed() &&
            $user->isDisabled()
        ) {
            // If deleting is enabled, the model supports soft deletes, the model
            // isn't already deleted, and the LDAP user is disabled, we'll
            // go ahead and delete the users model.
            $model->delete();

            if ($this->isLogging()) {
                logger()->info("Soft-deleted user {$user->getRdn()}. Their user account is disabled.");
            }
        }
    }

    /**
     * Returns true / false if the model is using soft deletes
     * by checking if the model contains a `trashed` method.
     *
     * @param Model $model
     *
     * @return bool
     */
    protected function isUsingSoftDeletes(Model $model) : bool
    {
        return method_exists($model, 'trashed');
    }
}
