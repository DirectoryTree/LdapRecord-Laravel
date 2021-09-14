<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Support\Arr;
use LdapRecord\Models\Model;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\Query\Builder;

class EmulatedBuilder extends Builder
{
    use EmulatesQueries;

    /**
     * Create a new Eloquent model builder.
     *
     * @param Model $model
     *
     * @return mixed
     */
    public function model(Model $model)
    {
        $builder = $this->determineBuilderFromModel($model);

        return (new $builder($this->connection))
            ->setBaseDn($this->baseDn)
            ->setModel($model);
    }

    /**
     * Determine the query builder to use for the model.
     *
     * @param Model $model
     *
     * @return string
     */
    protected function determineBuilderFromModel(Model $model)
    {
        switch (true) {
            case $model instanceof ActiveDirectory:
                return Emulated\ActiveDirectoryBuilder::class;
            case $model instanceof OpenLDAP:
                return Emulated\OpenLdapBuilder::class;
            default:
                return Emulated\ModelBuilder::class;
        }
    }

    /**
     * Process the database query results into an LDAP result set.
     *
     * @param array $results
     *
     * @return array
     */
    protected function process($results)
    {
        return array_map([$this, 'mergeAttributesAndTransformResult'], $results);
    }

    /**
     * Merge  and transform the result.
     *
     * @param array $result
     *
     * @return array
     */
    protected function mergeAttributesAndTransformResult($result)
    {
        return array_merge(
            $this->transform($result),
            $this->retrieveExtraAttributes($result)
        );
    }

    /**
     * Retrieve extra attributes that should be merged with the result.
     *
     * @param array $result
     *
     * @return array
     */
    protected function retrieveExtraAttributes($result)
    {
        $attributes = array_filter(['dn', $result['guid_key'] ?? null]);

        return array_map(function ($value) {
            return Arr::wrap($value);
        }, Arr::only($result, $attributes));
    }
}
