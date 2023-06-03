<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class MakeLdapModel extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ldap:make:model';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new LDAP model.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'LDAP Model';

    /**
     * {@inheritdoc}
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/model.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Ldap';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the model'],
        ];
    }
}
