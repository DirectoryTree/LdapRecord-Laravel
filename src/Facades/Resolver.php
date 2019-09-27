<?php

namespace LdapRecord\Laravel\Facades;

use Illuminate\Support\Facades\Facade;
use LdapRecord\Laravel\Resolvers\ResolverInterface;

/**
 * @method static void setConnection(string $connection)
 * @method static \LdapRecord\Models\Model|null byId(string $identifier)
 * @method static \LdapRecord\Models\Model|null byCredentials(array $credentials)
 * @method static \LdapRecord\Models\Model|null byModel(\Illuminate\Contracts\Auth\Authenticatable $model)
 * @method static boolean authenticate(\LdapRecord\Models\Model $user, array $credentials = [])
 * @method static \LdapRecord\Query\Model\Builder query()
 * @method static string getLdapDiscoveryAttribute()
 * @method static string getLdapAuthAttribute()
 * @method static string getDatabaseUsernameColumn()
 * @method static string getDatabaseIdColumn()
 *
 * @see \LdapRecord\Laravel\Resolvers\UserResolver
 */
class Resolver extends Facade
{
    /**
     * {@inheritdoc}
     */
    public static function getFacadeAccessor()
    {
        return ResolverInterface::class;
    }
}
