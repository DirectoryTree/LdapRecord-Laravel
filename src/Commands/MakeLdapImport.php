<?php

namespace LdapRecord\Laravel\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Input\InputArgument;

class MakeLdapImport extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'make:ldap-import';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new LDAP import.';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'LDAP Import';

    /**
     * {@inheritdoc}
     */
    protected function getStub()
    {
        return __DIR__.'/stubs/import.stub';
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
        return $rootNamespace.'\Ldap\Imports';
    }

    /**
     * Replace the namespace for the given stub.
     *
     * @param  string  $stub
     * @param  string  $name
     * @return $this
     */
    protected function replaceNamespace(&$stub, $name)
    {
        $stub = str_replace(
            ['NamespacedDummyModel', 'DummyModel'],
            [$this->getModelInput(), $this->getClassName($this->getModelInput())],
            $stub
        );

        parent::replaceClass($stub, $name);

        return $this;
    }

    /**
     * Get the class name from a namespaced fully qualified name.
     *
     * @param string $class
     *
     * @return string
     */
    protected function getClassName($class)
    {
        $parts = explode('\\', $class);

        return end($parts);
    }

    /**
     * Get the desired class name from the input.
     *
     * @return string
     */
    protected function getModelInput()
    {
        return trim($this->argument('model'));
    }

    /**
     * Get the console command arguments.
     *
     * @return array
     */
    protected function getArguments()
    {
        return [
            ['name', InputArgument::REQUIRED, 'The name of the LdapRecord import.'],
            ['model', InputArgument::REQUIRED, 'The name of the LdapRecord model to import.']
        ];
    }
}
