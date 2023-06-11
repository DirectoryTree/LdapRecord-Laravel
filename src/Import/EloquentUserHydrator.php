<?php

namespace LdapRecord\Laravel\Import;

class EloquentUserHydrator extends EloquentHydrator
{
    /**
     * The hydrators to use when importing.
     */
    protected array $hydrators = [
        Hydrators\GuidHydrator::class,
        Hydrators\DomainHydrator::class,
        Hydrators\PasswordHydrator::class,
        Hydrators\AttributeHydrator::class,
    ];
}
