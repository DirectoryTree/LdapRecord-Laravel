<?php

namespace LdapRecord\Laravel\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\DetectsSoftDeletes;
use LdapRecord\Laravel\Events\DeletedMissing;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\Model as LdapModel;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Query\Builder;

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

        $users = $this->getUsers($provider->getLdapUserRepository());

        if (($count = count($users)) === 0) {
            return $this->info('There were no users found to import.');
        } elseif ($count === 1) {
            $this->info("Found user [{$users[0]->getRdn()}].");
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
            $this->import($provider->getLdapUserImporter(), $users);

            $imported = count($this->imported);

            $this->info("Successfully imported / synchronized [$imported] user(s).");

            if ($this->isDeletingMissing()) {
                $this->deleteMissing($provider->getLdapUserImporter(), $provider->getLdapUserRepository());
            }
        } else {
            $this->info('Okay, no users were imported / synchronized.');
        }
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
     * Imports the specified users and returns the total
     * number of users successfully imported.
     *
     * @param LdapUserImporter $importer
     * @param array            $users
     *
     * @return void
     */
    public function import(LdapUserImporter $importer, array $users = [])
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
     * Soft-delete all users who are missing from the import.
     *
     * @param LdapUserImporter   $importer
     * @param LdapUserRepository $users
     *
     * @return void
     */
    public function deleteMissing(LdapUserImporter $importer, LdapUserRepository $users)
    {
        if (empty($this->imported)) {
            return;
        }

        if (! $this->isUsingSoftDeletes($eloquent = $importer->createEloquentModel())) {
            return;
        }

        $this->info('Soft-deleting all missing users...');

        $ldap = $users->createModel();

        $domain = $ldap->getConnectionName() ?? config('ldap.default');

        // Here we'll soft-delete all users whom have a 'guid' present
        // but are missing from our imported guid array and are from
        // our LDAP domain that has just been imported. This ensures
        // the deleted users are the ones from the same domain.
        $existing = $eloquent->newQuery()
            ->whereNotNull($eloquent->getLdapGuidColumn())
            ->where($eloquent->getLdapDomainColumn(), '=', $domain)
            ->pluck($eloquent->getLdapGuidColumn());

        $toDelete = $existing->diff($this->imported);

        if ($toDelete->isEmpty()) {
            return $this->info('No missing users found. None have been soft-deleted.');
        }

        $deleted = $eloquent->newQuery()
            ->whereNotNull($eloquent->getLdapGuidColumn())
            ->where($eloquent->getLdapDomainColumn(), '=', $domain)
            ->whereIn($eloquent->getLdapGuidColumn(), $toDelete->toArray())
            ->update([$eloquent->getDeletedAtColumn() => $deletedAt = now()]);

        $this->info("Successfully soft-deleted [$deleted] users.");

        $ids = $eloquent->newQuery()
            ->select($eloquent->getKeyName())
            ->onlyTrashed()
            ->whereIn($eloquent->getLdapGuidColumn(), $toDelete->toArray())
            ->get()
            ->pluck($eloquent->getKeyName());

        event(new DeletedMissing($ids, $ldap, $eloquent));
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

    /**
     * Retrieves users to be imported.
     *
     * @param LdapUserRepository $users
     *
     * @return \LdapRecord\Models\Model[]
     */
    public function getUsers(LdapUserRepository $users)
    {
        $this->applyQueryConstraints($query = $users->query());

        if ($user = $this->argument('user')) {
            return [$query->findByAnr($user)];
        }

        return $query->paginate()->toArray();
    }

    /**
     * Apply the LDAP query constraints, if needed.
     *
     * @param Builder $query
     *
     * @return void
     */
    protected function applyQueryConstraints(Builder $query)
    {
        // Here we will apply the attributes to select for
        // the LDAP query, effectively reducing memory
        // usage for larger query result sets.
        if ($attributes = $this->option('attributes')) {
            $query->select(explode(',', $attributes));
        }

        // Here we will apply the LDAP filter constraint
        // if the option was specified in the command,
        // effectively limiting the users returned.
        if ($filter = $this->option('filter')) {
            $query->rawFilter($filter);
        }
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
                logger()->info("Imported user [{$user->getRdn()}]");
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
            $this->isUsingSoftDeletes($model)
            && $model->trashed()
            && $this->userIsEnabled($user)
        ) {
            // If the model has soft-deletes enabled, the model is
            // currently deleted, and the LDAP user account
            // is enabled, we'll restore the users model.
            $model->restore();

            if ($this->isLogging()) {
                logger()->info("Restored user [{$user->getRdn()}]. Their user account has been re-enabled.");
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
            $this->isUsingSoftDeletes($model)
            && ! $model->trashed()
            && $this->userIsDisabled($user)
        ) {
            // If deleting is enabled, the model supports soft deletes, the model
            // isn't already deleted, and the LDAP user is disabled, we'll
            // go ahead and delete the users model.
            $model->delete();

            if ($this->isLogging()) {
                logger()->info("Soft-deleted user [{$user->getRdn()}]. Their user account is disabled.");
            }
        }
    }

    /**
     * Determine whether the user is enabled.
     *
     * @param LdapModel $user
     *
     * @return bool
     */
    protected function userIsEnabled(LdapModel $user)
    {
        return $this->getUserAccountControl($user) === null ? false : ! $this->userIsDisabled($user);
    }

    /**
     * Determines whether the user is disabled.
     *
     * @param LdapModel $user
     *
     * @return bool
     */
    protected function userIsDisabled(LdapModel $user)
    {
        return ($this->getUserAccountControl($user) & AccountControl::ACCOUNTDISABLE) === AccountControl::ACCOUNTDISABLE;
    }

    /**
     * Get the user account control integer from the user.
     *
     * @param LdapModel $user
     *
     * @return int|null
     */
    protected function getUserAccountControl(LdapModel $user)
    {
        return $user->getFirstAttribute('userAccountControl');
    }
}
