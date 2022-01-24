<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository;
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
use LdapRecord\Models\Collection;
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
            {--c|chunk= : Use chunked based importing by specifying how many records per chunk.}
            {--dm|delete-missing : Soft-delete all users that are missing from the import. }
            {--no-log : Disables logging successful and unsuccessful imports.}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = "Import LDAP users into the application's database";

    /**
     * The LDAP user import instance.
     *
     * @var LdapUserImporter
     */
    protected $importer;

    /**
     * The import progress bar indicator.
     *
     * @var ProgressBar|null
     */
    protected $progress;

    /**
     * Execute the console command.
     *
     * @param LdapUserImporter $importer
     * @param Repositry        $config
     *
     * @return void
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public function handle(LdapUserImporter $importer, Repository $config)
    {
        $config->set('ldap.logging', $this->isLogging());

        /** @var \LdapRecord\Laravel\Auth\DatabaseUserProvider $provider */
        $provider = Auth::createUserProvider($providerName = $this->argument('provider'));

        if (is_null($provider)) {
            return $this->error("Provider [{$providerName}] does not exist.");
        } elseif (! $provider instanceof UserProvider) {
            return $this->error("Provider [{$providerName}] is not configured for LDAP authentication.");
        } elseif (! $provider instanceof DatabaseUserProvider) {
            return $this->error("Provider [{$providerName}] is not configured for database synchronization.");
        }

        $this->registerEventListeners();

        $this->setImporter($importer);

        $this->applyImporterOptions($provider);

        ($perChunk = $this->option('chunk'))
            ? $this->beginChunkedImport($perChunk)
            : $this->beginImport();
    }

    /**
     * Begin importing users into the database.
     *
     * @return void
     */
    protected function beginImport()
    {
        $loaded = $this->importer->loadObjectsFromRepository($this->argument('user'));

        if ($loaded->count() === 0) {
            return $this->info('There were no users found to import.');
        } elseif ($loaded->count() === 1) {
            $this->info("Found user [{$loaded->first()->getRdn()}].");
        } else {
            $this->info("Found [{$loaded->count()}] user(s).");
        }

        $this->confirmAndDisplayObjects($loaded);

        $this->confirmAndExecuteImport();
    }

    /**
     * Begin importing users into the database by chunk.
     *
     * @param int $perChunk
     *
     * @return void
     */
    protected function beginChunkedImport($perChunk)
    {
        $total = 0;

        $this->importer->chunkObjectsFromRepository(function (Collection $objects) use (&$total) {
            $this->info("\nChunking... Found [{$objects->count()}] user(s).");

            $this->confirmAndDisplayObjects($objects);

            $imported = $this->confirmAndExecuteImport();

            $total = $total + $imported;
        }, $perChunk);

        $total
            ? $this->info("\nCompleted chunked import. Successfully imported [{$total}] user(s).")
            : $this->info("\nCompleted chunked import. No users were imported.");
    }

    /**
     * Confirm and execute the import.
     *
     * @return int
     */
    protected function confirmAndExecuteImport()
    {
        $imported = 0;

        if (
            ! $this->input->isInteractive()
            || $this->confirm('Would you like these users to be imported / synchronized?', $default = true)
        ) {
            $imported = $this->importer->execute()->count();

            $this->info("\n Successfully imported / synchronized [$imported] user(s).");
        } else {
            $this->info("\n Okay, no users were imported / synchronized.");
        }

        return $imported;
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

        Event::listen(Completed::class, function () {
            if ($this->progress) {
                $this->progress->finish();
            }
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
    }

    /**
     * Displays the given users in a table.
     *
     * @param Collection $objects
     *
     * @return void
     */
    protected function confirmAndDisplayObjects(Collection $objects)
    {
        if (! $this->input->isInteractive()) {
            return;
        }

        if (! $this->confirm('Would you like to display the user(s) to be imported / synchronized?', $default = false)) {
            return;
        }

        $rows = $objects->sortBy(function (Model $object) {
            return $object->getName();
        })->map(function (Model $object) {
            return [
                'dn' => $object->getDn(),
                'name' => $object->getRdn(),
            ];
        })->toArray();

        $this->table(['Name', 'Distinguished Name'], $rows);
    }

    /**
     * Apply the import options to the importer.
     *
     * @param DatabaseUserProvider $provider
     *
     * @return void
     */
    protected function applyImporterOptions(DatabaseUserProvider $provider)
    {
        $this->importer->setLdapUserRepository(
            $provider->getLdapUserRepository()
        );

        $this->importer->setLdapSynchronizer(
            $provider->getLdapUserSynchronizer()
        );

        if ($filter = $this->option('filter')) {
            $this->importer->setLdapRawFilter($filter);
        }

        if ($attributes = $this->option('attributes')) {
            $this->importer->setLdapRequestAttributes(explode(',', $attributes));
        }

        if ($this->isRestoring()) {
            $this->importer->restoreEnabledUsers();
        }

        if ($this->isDeleting()) {
            $this->importer->trashDisabledUsers();
        }

        if ($this->isDeletingMissing()) {
            $this->importer->enableSoftDeletes();
        }
    }

    /**
     * Set the importer to use.
     *
     * @param LdapUserImporter $importer
     *
     * @return void
     */
    protected function setImporter(LdapUserImporter $importer)
    {
        $this->importer = $importer;
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
