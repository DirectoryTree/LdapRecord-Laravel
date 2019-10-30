<?php

namespace LdapRecord\Laravel;

use LdapRecord\Connection;
use LdapRecord\ConnectionInterface;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Laravel\Validation\Validator;

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
     * Get the domains configuration.
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
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * Determine if the domain should auto connect.
     *
     * @return bool
     */
    public function shouldAutoConnect() : bool
    {
        return $this->shouldAutoConnect;
    }

    /**
     * Create a new domain authenticator.
     *
     * @return DomainAuthenticator
     */
    public function auth()
    {
        return app(DomainAuthenticator::class, ['domain' => $this]);
    }

    /**
     * Create a new domain user locator.
     *
     * @return DomainUserLocator
     */
    public function locate()
    {
        return app(DomainUserLocator::class, ['domain' => $this]);
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
     * Get the LDAP authentication username.
     *
     * @return array
     */
    public function getAuthUsername() : string
    {
        return 'userprincipalname';
    }

    /**
     * Get the LDAP authentication scopes.
     *
     * @return array
     */
    public function getAuthScopes() : array
    {
        return [];
    }

    /**
     * Get the LDAP authentication rules.
     *
     * @return array
     */
    public function getAuthRules() : array
    {
        return [];
    }
}
