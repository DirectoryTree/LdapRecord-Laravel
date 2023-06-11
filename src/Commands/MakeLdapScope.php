<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class MakeLdapScope extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ldap:make:scope';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new LDAP query scope.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'LDAP Scope';

    /**
     * {@inheritdoc}
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/scope.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Ldap\Scopes';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the scope'],
        ];
    }
}
