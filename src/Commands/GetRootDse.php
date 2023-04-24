<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use LdapRecord\Container;
use LdapRecord\Models\Entry;

class GetRootDse extends Command
{
    /**
     * The signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ldap:rootdse
                            {connection? : The name of the LDAP connection to fetch the Root DSE record from.}
                            {--attributes= : A comma separated list of Root DSE attributes to display.}';

    /**
     * The description of the console command.
     *
     * @var string
     */
    protected $description = 'Test the configured application LDAP connections.';

    /**
     * Execute the console command.
     *
     * @throws \LdapRecord\Models\ModelNotFoundException
     */
    public function handle(): int
    {
        $connection = $this->argument('connection') ?? Container::getDefaultConnectionName();

        $rootDse = Entry::getRootDse($connection);

        if ($selected = $this->option('attributes')) {
            $onlyAttributes = array_map('trim', explode(',', $selected));
        }

        $attributes = isset($onlyAttributes)
            ? Arr::only($rootDse->getAttributes(), $onlyAttributes)
            : $rootDse->getAttributes();

        if (! empty($attributes)) {
            foreach ($attributes as $attribute => $values) {
                $this->line("<fg=yellow>$attribute:</>");

                array_map(function ($value) {
                    $this->line("  $value");
                }, $values);

                $this->line('');
            }

            return static::SUCCESS;
        }

        if (isset($onlyAttributes)) {
            $this->error(
                sprintf('Attributes [%s] were not found in the Root DSE record.', implode(', ', $onlyAttributes))
            );
        } else {
            $this->error('No attributes were returned from the Root DSE query.');
        }

        return static::FAILURE;
    }
}
