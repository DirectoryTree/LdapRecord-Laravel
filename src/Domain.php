<?php

namespace LdapRecord\Laravel;

use LdapRecord\Connection;
use LdapRecord\ConnectionInterface;
use LdapRecord\Laravel\Database\Importer;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Laravel\Validation\Validator;
use LdapRecord\Laravel\Database\PasswordSynchronizer;

abstract class Domain
{
    /**
     * The LDAP domain name.
     *
     * @var string
     */
    protected $name;

    /**
     * The LDAP domain connection.
     *
     * @var ConnectionInterface|null
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
     * Get the configuration of the domain.
     *
     * @return array
     */
    abstract public function getConfig() : array;

    /**
     * Constructor.
     *
     * @param string $name
     */
    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Get the name of the domain.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get whether the domain is being automatically connected to.
     *
     * @return bool
     */
    public function shouldAutoConnect() : bool
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
     * Create a new domain importer.
     *
     * @return Importer
     */
    public function importer()
    {
        return new Importer($this);
    }

    /**
     * Create a new user validator.
     *
     * @param \LdapRecord\Models\Model                 $user
     * @param \Illuminate\Database\Eloquent\Model|null $model
     *
     * @return Validator
     */
    public function userValidator($user, $model = null)
    {
        $rules = [];

        foreach ($this->getAuthRules() as $rule) {
            $rules[] = new $rule($user, $model);
        }

        return new Validator($rules);
    }

    /**
     * Create a new password synchronizer.
     *
     * @return PasswordSynchronizer
     */
    public function passwordSynchronizer()
    {
        return new PasswordSynchronizer($this);
    }

    /**
     * Set the domains connection.
     *
     * @param ConnectionInterface $connection
     */
    public function setConnection(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the domains connection.
     *
     * @return ConnectionInterface|null
     */
    public function getConnection() : ?ConnectionInterface
    {
        return $this->connection;
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
