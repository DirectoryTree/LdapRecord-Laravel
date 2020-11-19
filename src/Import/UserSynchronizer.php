<?php

namespace LdapRecord\Laravel\Import;

class UserSynchronizer extends Synchronizer
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
