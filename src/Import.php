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
     * The import synchronizer.
     *
     * @var callable
     */
    protected $synchronizer;

    /**
     * Constructor.
     *
     * @param string $eloquent
     */
    public function __construct($eloquent)
    {
        $this->importer = $this->createImporter($eloquent);

        $this->synchronizer = function ($object, $database) {
            return tap($this->importer->synchronize($object, $database))->save();
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
            $database = $this->importer->createOrFindEloquentModel($object);

            call_user_func($this->synchronizer, $object, $database);

            $imported->push($database);
        }

        return $imported;
    }

    /**
     * Use a callback for synchronizing LDAP attributes with the database model.
     *
     * @param callable $synchronizer
     *
     * @return $this
     */
    public function syncUsing(callable $synchronizer)
    {
        $this->synchronizer = $synchronizer;

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
