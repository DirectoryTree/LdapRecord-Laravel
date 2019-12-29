<?php

namespace LdapRecord\Laravel;

use LdapRecord\Connection;
use LdapRecord\Laravel\Validation\Validator;
use LdapRecord\Models\ActiveDirectory\User;

class Domain
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
     * @var Connection|null
     */
    protected $connection;

    /**
     * Whether to automatically connect to the domain.
     *
     * @var bool
     */
    protected $shouldAutoConnect = true;

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
     * Get the domains configuration.
     *
     * @return array
     */
    public function getConfig()
    {
        return [];
    }

    /**
     * Determine if the domain should auto connect.
     *
     * @return bool
     */
    public function shouldAutoConnect()
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
     * @param Connection $connection
     */
    public function setConnection(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Get the domains connection.
     *
     * @return Connection|null
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get a new connection for the domain.
     *
     * @return Connection
     */
    public function getNewConnection()
    {
        return new Connection($this->getConfig());
    }

    /**
     * Get the LDAP model for the domain.
     *
     * @return string
     */
    public function getLdapModel()
    {
        return User::class;
    }

    /**
     * Get the LDAP authentication username.
     *
     * @return string
     */
    public function getAuthUsername()
    {
        return 'userprincipalname';
    }

    /**
     * Get the LDAP authentication scopes.
     *
     * @return array
     */
    public function getAuthScopes()
    {
        return [];
    }

    /**
     * Get the LDAP authentication rules.
     *
     * @return array
     */
    public function getAuthRules()
    {
        return [];
    }
}
