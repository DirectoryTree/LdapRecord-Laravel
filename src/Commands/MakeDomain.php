<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;

class MakeDomain extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:ldap-domain';

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
     * {@inheritdoc}
     */
    protected function getStub()
    {
        $types = [
            'sync' => __DIR__.'./Stubs/synchronized-domain.stub',
            'default' => __DIR__.'./Stubs/domain.stub',
        ];

        return $types[$this->option('type') == 'sync' ? 'sync' : 'default'];
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
            ['type', 't', InputOption::VALUE_OPTIONAL, 'The type of domain to create (synchronized / plain)'],
        ];
    }
}
