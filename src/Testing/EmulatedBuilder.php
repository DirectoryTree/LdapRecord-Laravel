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

        return (new $builder($model, $this))->setBaseDn($this->baseDn);
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
        $extra = [];

        if (isset($result['dn'])) {
            $extra['dn'] = Arr::wrap($result['dn']);
        }

        // Map the GUID to its correct key (e.g., 'objectguid')
        if (isset($result['guid_key'], $result['guid'])) {
            $extra[$result['guid_key']] = Arr::wrap($result['guid']);
        }

        return $extra;
    }
}
