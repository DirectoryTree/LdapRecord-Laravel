<?php

namespace LdapRecord\Laravel;

use LdapRecord\Query\Model\Builder;

abstract class Import
{
    /**
     * The LdapRecord model to use for importing.
     *
     * @var string
     */
    protected $model;

    /**
     * The LDAP importer instance.
     *
     * @var LdapImporter
     */
    protected $importer;

    /**
     * The import runner.
     *
     * @var callable
     */
    protected $importRunner;

    /**
     * Constructor.
     *
     * @param string $eloquent
     */
    public function __construct($eloquent)
    {
        $this->importer = $this->createImporter($eloquent);

        $this->importRunner = function ($object, $importer) {
            return tap($importer->run($object))->save();
        };
    }

    /**
     * Execute the import on the given Eloquent model.
     *
     * @param string $eloquent
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public static function run($eloquent)
    {
        return (new static($eloquent))->execute();
    }

    /**
     * Execute the import.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function execute()
    {
        $eloquent = $this->importer->createEloquentModel();

        $imported = $eloquent->newCollection();

        foreach ($this->getImportableObjects() as $object) {
            $imported->push(
                call_user_func($this->importRunner, $object, $this->importer)
            );
        }

        return $imported;
    }

    /**
     * The import runner to use for processing an import.
     *
     * @param callable $operation
     *
     * @return $this
     */
    public function importUsing(callable $operation)
    {
        $this->importRunner = $operation;

        return $this;
    }

    /**
     * Get the LDAP importer instance.
     *
     * @return LdapImporter
     */
    public function getImporter()
    {
        return $this->importer;
    }

    /**
     * Get the importable LDAP objects.
     *
     * @return \LdapRecord\Query\Collection
     */
    protected function getImportableObjects()
    {
        $query = (new $this->model)->query();

        $this->applyConstraints($query);

        return $query->paginate();
    }

    /**
     * Apply any LDAP query constraints for importing.
     *
     * @param Builder $query
     *
     * @return void
     */
    protected function applyConstraints(Builder $query)
    {
        //
    }

    /**
     * Create a new LDAP importer instance.
     *
     * @param string $eloquent
     *
     * @return LdapImporter
     */
    protected function createImporter($eloquent)
    {
        return new LdapImporter($eloquent, $this->config());
    }

    /**
     * The configuration definition for the import.
     *
     * @return array
     */
    protected function config()
    {
        return [];
    }
}
