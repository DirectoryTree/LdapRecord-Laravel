<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Support\Arr;
use LdapRecord\Models\Model;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Models\Types\OpenLDAP;
use LdapRecord\Query\Builder;
use LdapRecord\Query\Model\Builder as ModelBuilder;

class EmulatedBuilder extends Builder
{
    use EmulatesQueries;

    /**
     * Create a new Eloquent model builder.
     *
     * @return mixed
     */
    public function model(Model $model): ModelBuilder
    {
        $builder = $this->determineBuilderFromModel($model);

        return (new $builder($this->connection))
            ->setBaseDn($this->baseDn)
            ->setModel($model);
    }

    /**
     * Determine the query builder to use for the model.
     */
    protected function determineBuilderFromModel(Model $model): string
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
     */
    protected function process(array $results): array
    {
        return array_map([$this, 'mergeAttributesAndTransformResult'], $results);
    }

    /**
     * Merge  and transform the result.
     */
    protected function mergeAttributesAndTransformResult(array $result): array
    {
        return array_merge(
            $this->transform($result),
            $this->retrieveExtraAttributes($result)
        );
    }

    /**
     * Retrieve extra attributes that should be merged with the result.
     */
    protected function retrieveExtraAttributes(array $result): array
    {
        $attributes = array_filter(['dn', $result['guid_key'] ?? null]);

        return array_map(function ($value) {
            return Arr::wrap($value);
        }, Arr::only($result, $attributes));
    }
}
