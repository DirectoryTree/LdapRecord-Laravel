<?php

namespace LdapRecord\Laravel;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Laravel\Validation\Validator;
use LdapRecord\Models\ActiveDirectory\User;

class Domain
{
    /**
     * Whether to automatically connect to the domain.
     *
     * @var bool
     */
    public static $shouldAutoConnect = true;

    /**
     * The LDAP model class name.
     *
     * @var string
     */
    public static $ldapModel = User::class;

    /**
     * Determine if the domain should auto connect.
     *
     * @param bool $enabled
     */
    public static function shouldAutoConnect($enabled = true)
    {
        static::$shouldAutoConnect = $enabled;
    }

    /**
     * Get the domains configuration.
     *
     * @return array
     */
    public static function getConfig()
    {
        return [];
    }

    /**
     * Get the LDAP model for the domain.
     *
     * @return string
     */
    public static function getLdapModel()
    {
        return User::class;
    }

    /**
     * Get the LDAP authentication username.
     *
     * @return string
     */
    public static function getAuthUsername()
    {
        return 'userprincipalname';
    }

    /**
     * Get the LDAP authentication scopes.
     *
     * @return string[]
     */
    public static function getAuthScopes()
    {
        return [];
    }

    /**
     * Get the LDAP authentication rules.
     *
     * @return string[]
     */
    public static function getAuthRules()
    {
        return [];
    }

    /**
     * Get a new LDAP model instance.
     *
     * @return \LdapRecord\Models\Model
     */
    public static function ldapModel()
    {
        return new static::$ldapModel;
    }

    /**
     * Get the authentication scopes for the domain.
     *
     * @return \Illuminate\Support\Collection
     */
    public static function scopes()
    {
        return collect(static::getAuthScopes())->map(function ($scope) {
            return app($scope);
        })->values();
    }

    /**
     * Get the authentication rules for the domain.
     *
     * @param \LdapRecord\Models\Model                 $user
     * @param \Illuminate\Database\Eloquent\Model|null $model
     *
     * @return \Illuminate\Support\Collection
     */
    public static function rules($user, $model = null)
    {
        return collect(static::getAuthRules())->map(function ($rule) use ($user, $model) {
            return new $rule($user, $model);
        })->values();
    }

    /**
     * Create a new domain authenticator.
     *
     * @return DomainAuthenticator
     */
    public static function auth()
    {
        return new DomainAuthenticator(static::resolveConnection()->auth());
    }

    /**
     * Create a new domain user locator.
     *
     * @return DomainUserLocator
     */
    public static function locate()
    {
        return new DomainUserLocator(new static);
    }

    /**
     * Create a new user validator.
     *
     * @param \LdapRecord\Models\Model                 $user
     * @param \Illuminate\Database\Eloquent\Model|null $model
     *
     * @return Validator
     */
    public static function validator($user, $model = null)
    {
        return new Validator(static::rules($user, $model));
    }

    /**
     * Get the domains connection.
     *
     * @return Connection
     *
     * @throws \LdapRecord\ContainerException
     */
    public static function resolveConnection()
    {
        return Container::getConnection(get_called_class());
    }

    /**
     * Get a new connection for the domain.
     *
     * @return Connection
     */
    public static function getNewConnection()
    {
        return new Connection(static::getConfig());
    }
}
