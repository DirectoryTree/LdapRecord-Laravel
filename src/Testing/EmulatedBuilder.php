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
        switch (true) {
            case $model instanceof ActiveDirectory:
                $instance = Emulated\ActiveDirectoryBuilder::class;
                break;
            case $model instanceof OpenLDAP:
                $instance = Emulated\OpenLdapBuilder::class;
                break;
            default:
                $instance = Emulated\ModelBuilder::class;
                break;
        }

        return (new $instance($this->connection))
            ->setModel($model)
            ->in($this->dn);
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
        return array_map(function ($result) {
            return array_merge(
                $this->transform($result), $this->retrieveExtraAttributes($result)
            );
        }, $results);
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
