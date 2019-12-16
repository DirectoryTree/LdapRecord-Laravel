<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

class MakeDomain extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'ldap:domain';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new LDAP domain for authentication';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'LDAP Domain';

    /**
     * @inheritDoc
     */
    protected function getStub()
    {
        return __DIR__.'/Stubs/domain.stub';
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Ldap';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the domain'],
        ];
    }

    /**
     * Get the console command options.
     *
     * @return array
     */
    protected function getOptions()
    {
        return [
            ['type', 't', InputOption::VALUE_OPTIONAL, 'The type of domain to create']
        ];
    }
}
