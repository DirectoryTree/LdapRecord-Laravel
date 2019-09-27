<?php

namespace LdapRecord\Laravel\Resolvers;

use RuntimeException;
use LdapRecord\Container;
use Illuminate\Support\Arr;
use LdapRecord\Models\Model;
use LdapRecord\ConnectionInterface;
use LdapRecord\Query\Model\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Config;
use Illuminate\Contracts\Auth\UserProvider;
use LdapRecord\Laravel\Events\Authenticated;
use LdapRecord\Laravel\Events\Authenticating;
use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\Events\AuthenticationFailed;

class UserResolver implements ResolverInterface
{
    /**
     * The name of the LDAP connection to utilize.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * {@inheritdoc}
     */
    public function setConnection($connection)
    {
        $this->connection = $connection;
    }

    /**
     * {@inheritdoc}
     */
    public function byId($identifier)
    {
        if ($user = $this->query()->findByGuid($identifier)) {
            return $user;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function byCredentials(array $credentials = [])
    {
        if (empty($credentials)) {
            return;
        }

        // Depending on the configured user provider, the
        // username field will differ for retrieving
        // users by their credentials.
        $attribute = $this->getAppAuthProvider() instanceof NoDatabaseUserProvider ?
            $this->getLdapDiscoveryAttribute() :
            $this->getDatabaseUsernameColumn();

        if (! array_key_exists($attribute, $credentials)) {
            throw new RuntimeException(
                "The '$attribute' key is missing from the given credentials array."
            );
        }

        return $this->query()->whereEquals(
            $this->getLdapDiscoveryAttribute(),
            $credentials[$attribute]
        )->first();
    }

    /**
     * {@inheritdoc}
     */
    public function byModel(Authenticatable $model)
    {
        return $this->byId($model->{$this->getDatabaseIdColumn()});
    }

    /**
     * {@inheritdoc}
     */
    public function authenticate(Model $user, array $credentials = [])
    {
        $attribute = $this->getLdapAuthAttribute();

        if (in_array($attribute, ['dn', 'distinguishedname'])) {
            $username = $user->getDn();
        } else {
            $username = $user->getFirstAttribute($attribute);
        }

        $password = $this->getPasswordFromCredentials($credentials);

        Event::dispatch(new Authenticating($user, $username));

        if ($this->getLdapConnection()->auth()->attempt($username, $password)) {
            Event::dispatch(new Authenticated($user));

            return true;
        }

        Event::dispatch(new AuthenticationFailed($user));

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function query() : Builder
    {
        $query = $this->getLdapConnection()->search()->users();

        // We will ensure our object GUID attribute is always selected
        // along will all attributes. Otherwise, if the object GUID
        // attribute is virtual, it may not be returned.
        $selects = array_unique(array_merge(['*', $query->getSchema()->objectGuid()], $query->getSelects()));

        $query->select($selects);

        foreach ($this->getQueryScopes() as $scope) {
            app($scope)->apply($query);
        }

        return $query;
    }

    /**
     * {@inheritdoc}
     */
    public function getLdapDiscoveryAttribute() : string
    {
        return Config::get('ldap_auth.identifiers.ldap.locate_users_by', 'userprincipalname');
    }

    /**
     * {@inheritdoc}
     */
    public function getLdapAuthAttribute() : string
    {
        return Config::get('ldap_auth.identifiers.ldap.bind_users_by', 'distinguishedname');
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseUsernameColumn() : string
    {
        return Config::get('ldap_auth.identifiers.database.username_column', 'email');
    }

    /**
     * {@inheritdoc}
     */
    public function getDatabaseIdColumn() : string
    {
        return Config::get('ldap_auth.identifiers.database.guid_column', 'objectguid');
    }

    /**
     * Returns the password field to retrieve from the credentials.
     *
     * @param array $credentials
     *
     * @return string|null
     */
    protected function getPasswordFromCredentials($credentials)
    {
        return Arr::get($credentials, 'password');
    }

    /**
     * Retrieves the provider for the current connection.
     *
     * @throws \LdapRecord\ContainerException
     *
     * @return ConnectionInterface
     */
    protected function getLdapConnection() : ConnectionInterface
    {
        return  Container::getInstance()->get($this->connection ?? $this->getLdapAuthConnectionName());
    }

    /**
     * Returns the default guards provider instance.
     *
     * @return UserProvider
     */
    protected function getAppAuthProvider() : UserProvider
    {
        return Auth::guard()->getProvider();
    }

    /**
     * Returns the connection name of the authentication provider.
     *
     * @return string
     */
    protected function getLdapAuthConnectionName()
    {
        return Config::get('ldap_auth.connection', 'default');
    }

    /**
     * Returns the configured query scopes.
     *
     * @return array
     */
    protected function getQueryScopes()
    {
        return Config::get('ldap_auth.scopes', []);
    }
}
