<?php

namespace LdapRecord\Laravel\Testing;

use Generator;
use Illuminate\Support\Arr;
use LdapRecord\Query\Builder;

class EmulatedBuilder extends Builder
{
    use EmulatesQueries;

    /**
     * Runs the chunk operation with the given filter.
     */
    protected function runChunk(string $filter, int $perPage, bool $isCritical): Generator
    {
        yield from array_chunk(
            $this->parse($this->run($filter)),
            $perPage
        );
    }

    /**
     * Process the database query results into an LDAP result set.
     */
    protected function process(array $results): array
    {
        return array_map(fn (array $result) => array_merge(
            $this->transform($result),
            $this->retrieveExtraAttributes($result)
        ), $results);
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
