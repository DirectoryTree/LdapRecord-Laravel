<?php

namespace LdapRecord\Laravel\Import;

class UserSynchronizer extends Synchronizer
{
    /**
     * Get the class name of the hydrator to use.
     *
     * @return class-string
     */
    protected function hydrator(): string
    {
        return EloquentUserHydrator::class;
    }
}
