<?php

namespace LdapRecord\Laravel;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class LdapUserHydrator
{
    /**
     * @var Hydrators\AttributeHydrator[]
     */
    protected static $hydrators = [
        Hydrators\GuidHydrator::class,
        Hydrators\DomainHydrator::class,
        Hydrators\AttributeHydrator::class,
        Hydrators\PasswordHydrator::class,
    ];

    /**
     * Hydrate the database model with the LDAP user.
     *
     * @param LdapModel     $user
     * @param EloquentModel $database
     * @param array         $attributes
     *
     * @return void
     */
    public static function hydrate(LdapModel $user, EloquentModel $database, array $attributes = [])
    {
        foreach (static::$hydrators as $hydrator) {
            $hydrator::with($attributes)->hydrate($user, $database);
        }
    }
}
