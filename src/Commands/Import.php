<?php

namespace LdapRecord\Laravel\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Model as LdapModel;
use RuntimeException;

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
    protected $signature = 'ldap:import {provider : The authentication provider to import.}
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

        $users = $this->getUsers($provider->getLdapUserRepository());

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
            $imported = $this->import($provider->getLdapUserImporter(), $users);

            $this->info("Successfully imported / synchronized {$imported} user(s).");
        } else {
            $this->info('Okay, no users were imported / synchronized.');
        }
    }

    /**
     * Imports the specified users and returns the total
     * number of users successfully imported.
     *
     * @param LdapUserImporter $importer
     * @param array            $users
     *
     * @return int
     */
    public function import(LdapUserImporter $importer, array $users = [])
    {
        $imported = 0;

        $this->output->progressStart(count($users));

        /** @var LdapModel $user */
        foreach ($users as $user) {
            try {
                // Import the user and retrieve it's model.
                $model = $importer->run($user);

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
                    logger()->error("Importing user [{$user->getRdn()}] failed. {$e->getMessage()}");
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
    public function isLogging(): bool
    {
        return ! $this->option('no-log');
    }

    /**
     * Returns true / false if users are being deleted
     * if their account is disabled in LDAP.
     *
     * @return bool
     */
    public function isDeleting(): bool
    {
        return $this->option('delete') == 'true';
    }

    /**
     * Returns true / false if users are being restored
     * if their account is enabled in LDAP.
     *
     * @return bool
     */
    public function isRestoring(): bool
    {
        return $this->option('restore') == 'true';
    }

    /**
     * Retrieves users to be imported.
     *
     * @param LdapUserRepository $users
     *
     * @return \LdapRecord\Models\Model[]
     */
    public function getUsers(LdapUserRepository $users)
    {
        $query = $users->query();

        if ($filter = $this->option('filter')) {
            // If the filter option was given, we'll
            // insert it into our search query.
            $query->rawFilter($filter);
        }

        if ($user = $this->argument('user')) {
            return [$query->findByAnr($user)];
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
    protected function save(LdapModel $user, Model $model)
    {
        if ($model->save() && $model->wasRecentlyCreated) {
            event(new Imported($user, $model));

            // Log the successful import.
            if ($this->isLogging()) {
                logger()->info("Imported user '{$user->getRdn()}'");
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
                logger()->info("Restored user '{$user->getRdn()}'. Their user account has been re-enabled.");
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
                logger()->info("Soft-deleted user '{$user->getRdn()}'. Their user account is disabled.");
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
    protected function isUsingSoftDeletes(Model $model)
    {
        return method_exists($model, 'trashed');
    }
}
