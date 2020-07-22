<?php

namespace LdapRecord\Laravel;

use LdapRecord\Query\Model\Builder;
use Illuminate\Database\Eloquent\Model;

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
     * Constructor.
     *
     * @param string $eloquent
     */
    public function __construct($eloquent)
    {
        $this->importer = $this->createImporter($eloquent);
    }

    /**
     * The configuration definition for the import.
     *
     * @return array
     */
    abstract protected function config();

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
            $database = $this->importer->run($object);

            $this->save($database);

            $imported->push($database);
        }

        return $imported;
    }

    /**
     * Save the database model.
     *
     * @param Model $model
     *
     * @return void
     */
    protected function save(Model $model)
    {
        $model->save();
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
     * Apply any query constraints for the import.
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
        $importer = $this->getImporter();

        return new $importer($eloquent, $this->config());
    }

    /**
     * Get the class name of the LDAP importer.
     *
     * @return string
     */
    protected function getImporter()
    {
        return LdapImporter::class;
    }
}
