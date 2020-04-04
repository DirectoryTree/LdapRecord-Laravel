<?php

namespace LdapRecord\Laravel;

class LdapUserImporter extends LdapImporter
{
    /**
     * Get the class name of the hydrator to use.
     *
     * @return string
     */
    protected function hydrator()
    {
        return EloquentUserHydrator::class;
    }
}
