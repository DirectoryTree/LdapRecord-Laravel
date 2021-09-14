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
    protected $name = 'make:ldap-rule';

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
     * @inheritdoc
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/rule.stub';
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
        return $rootNamespace.'\Ldap\Rules';
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the rule'],
        ];
    }
}
