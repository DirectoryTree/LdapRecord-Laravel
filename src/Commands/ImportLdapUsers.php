<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\DetectsSoftDeletes;
use LdapRecord\Laravel\Events\Import\Completed;
use LdapRecord\Laravel\Events\Import\DeletedMissing;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\ImportFailed;
use LdapRecord\Laravel\Events\Import\Started;
use LdapRecord\Models\Model;
use Symfony\Component\Console\Helper\ProgressBar;

class ImportLdapUsers extends Command
{
    use DetectsSoftDeletes;

    /**
     * The signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:import {provider=ldap : The authentication provider to import.}
            {user? : The specific user to import.}
            {--f|filter= : The raw LDAP filter for limiting users imported.}
            {--a|attributes= : Comma separated list of LDAP attributes to select. }
            {--d|delete : Soft-delete the users model if their LDAP account is disabled.}
            {--r|restore : Restores soft-deleted models if their LDAP account is enabled.}
            {--dm|delete-missing : Soft-delete all users that are missing from the import. }
            {--no-log : Disables logging successful and unsuccessful imports.}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Imports LDAP users into the application database.';

    /**
     * The LDAP user import instance.
     *
     * @var LdapUserImporter
     */
    protected $import;

    /**
     * The LDAP objects being imported.
     *
     * @var \LdapRecord\Query\Collection
     */
    protected $objects;

    /**
     * The import progress bar indicator.
     *
     * @var ProgressBar|null
     */
    protected $progress;

    /**
     * Constructor.
     *
     * @param LdapUserImporter $import
     */
    public function __construct(LdapUserImporter $import)
    {
        parent::__construct();

        $this->import = $import;
    }

    /**
     * Execute the console command.
     *
     * @return void
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public function handle()
    {
        $this->registerEventListeners();

        config(['ldap.logging' => $this->isLogging()]);

        /** @var \LdapRecord\Laravel\Auth\DatabaseUserProvider $provider */
        $provider = Auth::createUserProvider($providerName = $this->argument('provider'));

        if (is_null($provider)) {
            return $this->error("Provider [{$providerName}] does not exist.");
        } elseif (! $provider instanceof UserProvider) {
            return $this->error("Provider [{$providerName}] is not configured for LDAP authentication.");
        } elseif (! $provider instanceof DatabaseUserProvider) {
            return $this->error("Provider [{$providerName}] is not configured for database synchronization.");
        }

        $this->applyCommandOptions();
        $this->applyProviderRepository($provider);
        $this->applyProviderSynchronizer($provider);

        $this->objects = $this->import->loadObjectsFromRepository($this->argument('user'));

        if ($this->objects->count() === 0) {
            return $this->info('There were no users found to import.');
        }

        if ($this->objects->count() === 1) {
            $this->info("Found user [{$this->objects->first()->getRdn()}].");
        } else {
            $this->info("Found [{$this->objects->count()}] user(s).");
        }

        $this->confirmAndDisplayObjects();

        $this->confirmAndExecuteImport();
    }

    /**
     * Confirm and execute the import.
     *
     * @return void
     */
    protected function confirmAndExecuteImport()
    {
        if (
            ! $this->input->isInteractive()
            || $this->confirm('Would you like these users to be imported / synchronized?', $default = true)
        ) {
            $imported = $this->import->execute()->count();

            $this->info("\n Successfully imported / synchronized [$imported] user(s).");
        } else {
            $this->info("\n Okay, no users were imported / synchronized.");
        }
    }

    /**
     * Register the import event callbacks for the command.
     *
     * @return void
     */
    protected function registerEventListeners()
    {
        Event::listen(Started::class, function (Started $event) {
            $this->progress = $this->output->createProgressBar($event->objects->count());
        });

        Event::listen(Imported::class, function () {
            if ($this->progress) {
                $this->progress->advance();
            }
        });

        Event::listen(ImportFailed::class, function () {
            if ($this->progress) {
                $this->progress->advance();
            }
        });

        Event::listen(DeletedMissing::class, function (DeletedMissing $event) {
            $event->deleted->isEmpty()
                ? $this->info("\n No missing users found. None have been soft-deleted.")
                : $this->info("\n Successfully soft-deleted [{$event->deleted->count()}] users.");
        });

        Event::listen(Completed::class, function (Completed $event) {
            if ($this->progress) {
                $this->progress->finish();
            }
        });
    }

    /**
     * Prepare the import by applying the command options.
     *
     * @return void
     */
    protected function applyCommandOptions()
    {
        if ($filter = $this->option('filter')) {
            $this->import->setLdapRawFilter($filter);
        }

        if ($attributes = $this->option('attributes')) {
            $this->import->setLdapRequestAttributes(explode(',', $attributes));
        }

        if ($this->isRestoring()) {
            $this->import->restoreEnabledUsers();
        }

        if ($this->isDeleting()) {
            $this->import->trashDisabledUsers();
        }

        if ($this->isDeletingMissing()) {
            $this->import->enableSoftDeletes();
        }
    }

    /**
     * Set the synchronizer to use on the import.
     *
     * @param DatabaseUserProvider $provider
     */
    protected function applyProviderSynchronizer(DatabaseUserProvider $provider)
    {
        $this->import->setLdapSynchronizer($provider->getLdapUserSynchronizer());
    }

    /**
     * Set the repository to use on the import.
     *
     * @param DatabaseUserProvider $provider
     */
    protected function applyProviderRepository(DatabaseUserProvider $provider)
    {
        $this->import->setLdapUserRepository($provider->getLdapUserRepository());
    }

    /**
     * Displays the given users in a table.
     *
     * @return void
     */
    protected function confirmAndDisplayObjects()
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        if (! $this->confirm('Would you like to display the user(s) to be imported / synchronized?', $default = false)) {
            return;
        }

        $headers = ['Name', 'Distinguished Name'];

        $rows = $this->objects->sortBy(function (Model $object) {
            return $object->getName();
        })->map(function (Model $object) {
            return [
                'name' => $object->getRdn(),
                'dn' => $object->getDn(),
            ];
        })->toArray();

        $this->table($headers, $rows);
    }

    /**
     * Determine if logging is enabled.
     *
     * @return bool
     */
    protected function isLogging()
    {
        return ! $this->option('no-log');
    }

    /**
     * Determine if soft-deleting disabled user accounts is enabled.
     *
     * @return bool
     */
    protected function isDeleting()
    {
        return $this->option('delete') == 'true';
    }

    /**
     * Determine if soft-deleting all missing users is enabled.
     *
     * @return bool
     */
    protected function isDeletingMissing()
    {
        return $this->option('delete-missing') == 'true' && is_null($this->argument('user'));
    }

    /**
     * Determine if restoring re-enabled users is enabled.
     *
     * @return bool
     */
    protected function isRestoring()
    {
        return $this->option('restore') == 'true';
    }
}
