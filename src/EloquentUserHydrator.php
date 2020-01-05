<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class EloquentUserHydrator
{
    /**
     * @var Hydrators\AttributeHydrator[]
     */
    protected static $hydrators = [
        Hydrators\GuidHydrator::class,
        Hydrators\DomainHydrator::class,
        Hydrators\AttributeHydrator::class,
    ];

    /**
     * Hydrate the database model with the LDAP user.
     *
     * @param LdapModel     $user
     * @param EloquentModel $database
     * @param array         $config
     *
     * @return void
     */
    public static function hydrate(LdapModel $user, EloquentModel $database, array $config = [])
    {
        foreach (static::$hydrators as $hydrator) {
            $hydrator::with($config)->hydrate($user, $database);
        }
    }
}
