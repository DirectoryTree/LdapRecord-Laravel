<?php

namespace LdapRecord\Laravel;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\ConnectionInterface;
use LdapRecord\Models\ActiveDirectory\User;

abstract class Domain
{
    /**
     * The LDAP domain connection.
     *
     * @var Connection
     */
    protected $connection;

    /**
     * Whether to automatically connect to the domain.
     *
     * @var bool
     */
    protected $shouldAutoConnect = true;

    /**
     * Whether the domain uses the local database to store users.
     *
     * @var bool
     */
    protected $usesDatabase = false;

    /**
     * Whether passwords should be synchronized to the local database.
     *
     * @var bool
     */
    protected $syncPasswords = false;

    /**
     * Whether authentication attempts should fall back to the local database.
     *
     * @var bool
     */
    protected $loginFallback = false;

    /**
     * Get the name of the domain.
     *
     * @return string
     */
    abstract public function getName() : string;

    /**
     * Get the configuration of the domain.
     *
     * @return array
     */
    abstract public function getConfig() : array;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->connection = $this->getConnection();
    }

    /**
     * Get whether the domain is being automatically connected to.
     *
     * @return bool
     */
    public function isAutoConnecting() : bool
    {
        return $this->shouldAutoConnect;
    }

    /**
     * Get whether the domain is falling back to local database authentication.
     *
     * @return bool
     */
    public function isFallingBack() : bool
    {
        return $this->loginFallback;
    }

    /**
     * Get whether the domain is syncing passwords to the local database.
     *
     * @return bool
     */
    public function isSyncingPasswords() : bool
    {
        return $this->syncPasswords;
    }

    /**
     * Get whether the domain is syncing to the local database.
     *
     * @return bool
     */
    public function isUsingDatabase() : bool
    {
        return $this->usesDatabase;
    }

    /**
     * Connect to the LDAP domain.
     *
     * @throws \LdapRecord\Auth\BindException
     * @throws \LdapRecord\ConnectionException
     */
    public function connect()
    {
        $this->getConnection()->connect();
    }

    /**
     * Create a new domain authenticator.
     *
     * @return DomainAuthenticator
     */
    public function auth()
    {
        return new DomainAuthenticator($this);
    }

    /**
     * Create a new domain user locator.
     *
     * @return DomainUserLocator
     */
    public function locate()
    {
        return new DomainUserLocator($this);
    }

    /**
     * Get the domains connection.
     *
     * @return ConnectionInterface
     */
    public function getConnection() : ConnectionInterface
    {
        $container = Container::getInstance();

        if ($container->exists($this->getName())) {
            return $container->get($this->getName());
        }

        $connection = $this->getNewConnection();

        $container->add($connection, $this->getName());

        return $connection;
    }

    /**
     * Get a new connection for the domain.
     *
     * @return Connection
     */
    public function getNewConnection() : ConnectionInterface
    {
        return new Connection($this->getConfig());
    }

    /**
     * Get the LDAP model for the domain.
     *
     * @return string
     */
    public function getLdapModel() : string
    {
        return User::class;
    }

    /**
     * Get the attributes to sync.
     *
     * @return array|null
     */
    public function getSyncAttributes() : ?array
    {
        return ['name' => 'cn'];
    }

    /**
     * Get the eloquent database model for the domain.
     *
     * @return string|null
     */
    public function getDatabaseModel() : ?string
    {
        //
    }

    /**
     * Get the name of the database column that stores the users object GUID.
     *
     * @return string|null
     */
    public function getDatabaseGuidColumn() : ?string
    {
        return 'objectguid';
    }

    /**
     * Get the name of the database column that stores the users username.
     *
     * @return string|null
     */
    public function getDatabaseUsernameColumn() : ?string
    {
        return 'email';
    }

    /**
     * Get the name of the database column that stores the users password.
     *
     * @return string|null
     */
    public function getDatabasePasswordColumn() : ?string
    {
        return 'password';
    }

    /**
     * Get the attribute for authenticating users with.
     *
     * @return string
     */
    public function getAuthUsername() : string
    {
        return 'userprincipalname';
    }

    /**
     * Get the authentication scopes for the domain.
     *
     * @return array
     */
    public function getAuthScopes() : array
    {
        return [];
    }

    /**
     * Get the authentication rules for the domain.
     *
     * @return array
     */
    public function getAuthRules() : array
    {
        return [];
    }
}
