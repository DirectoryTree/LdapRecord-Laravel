<?php

namespace LdapRecord\Laravel\Resolvers;

use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;
use Illuminate\Contracts\Auth\Authenticatable;

interface ResolverInterface
{
    /**
     * Sets the LDAP connection to use.
     *
     * @param string $connection
     *
     * @return void
     */
    public function setConnection($connection);

    /**
     * Retrieves a user by the given identifier.
     *
     * @param string $identifier The users object GUID.
     *
     * @return Model|null
     */
    public function byId($identifier);

    /**
     * Retrieve a user by the given credentials.
     *
     * @param array $credentials The users credentials
     *
     * @return Model|null
     */
    public function byCredentials(array $credentials = []);

    /**
     * Retrieve a user by the given model.
     *
     * @param Authenticatable $model The users eloquent model
     *
     * @return Model|null
     */
    public function byModel(Authenticatable $model);

    /**
     * Authenticates the user against the current provider.
     *
     * @param Model $user        The LDAP users model
     * @param array $credentials The LDAP users credentials
     *
     * @return bool
     */
    public function authenticate(Model $user, array $credentials = []);

    /**
     * Returns a new user query.
     *
     * @return Builder
     */
    public function query() : Builder;

    /**
     * Retrieves the configured LDAP users username attribute.
     *
     * @return string
     */
    public function getLdapDiscoveryAttribute() : string;

    /**
     * Retrieves the configured LDAP users authenticatable username attribute.
     *
     * @return string
     */
    public function getLdapAuthAttribute() : string;

    /**
     * Retrieves the configured database username attribute.
     *
     * @return string
     */
    public function getDatabaseUsernameColumn() : string;

    /**
     * Retrieves the configured database identifier attribute.
     *
     * @return string
     */
    public function getDatabaseIdColumn() : string;
}
