<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class MakeLdapRule extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ldap:make:rule';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new LDAP authentication rule.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'LDAP Rule';

    /**
     * {@inheritdoc}
     */
    protected function getStub(): string
    {
        return __DIR__.'/stubs/rule.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Ldap\Rules';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the rule'],
        ];
    }
}
