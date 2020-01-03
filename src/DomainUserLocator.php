<?php

namespace LdapRecord\Laravel;

use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Scopes\ScopeInterface;
use LdapRecord\Query\Model\Builder;
use RuntimeException;

class DomainUserLocator
{
    /**
     * The LDAP domain to retrieve users from.
     *
     * @var Domain
     */
    protected $domain;

    /**
     * Constructor.
     *
     * @param Domain $domain
     */
    public function __construct(Domain $domain)
    {
        $this->domain = $domain;
    }

    /**
     * Get a user by the attribute and value.
     *
     * @param string $attribute
     * @param string $value
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function by($attribute, $value)
    {
        return $this->query()->findBy($attribute, $value);
    }

    /**
     * Get a user by their object GUID.
     *
     * @param string $guid
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function byGuid($guid)
    {
        if ($user = $this->query()->findByGuid($guid)) {
            return $user;
        }
    }

    /**
     * Retrieve a user by the given credentials.
     *
     * @param array $credentials The users credentials
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function byCredentials(array $credentials = [])
    {
        if (empty($credentials)) {
            return;
        }

        $usernameKey = $this->domain instanceof SynchronizedDomain ?
            $this->domain->getDatabaseUsernameColumn() :
            $this->domain->getAuthUsername();

        if (! array_key_exists($usernameKey, $credentials)) {
            throw new RuntimeException(
                "The [$usernameKey] key is missing from the given credentials array."
            );
        }

        return $this->query()->whereEquals(
            $this->domain->getAuthUsername(),
            $credentials[$usernameKey]
        )->first();
    }

    /**
     * Get a user by their eloquent model.
     *
     * @param LdapAuthenticatable $model
     *
     * @return \LdapRecord\Models\Model|null
     */
    public function byModel(LdapAuthenticatable $model)
    {
        return $this->byGuid($model->{$model->getLdapGuid()});
    }

    /**
     * Get a new user query for the domain.
     *
     * @return Builder
     */
    public function query(): Builder
    {
        $model = $this->domain->ldapModel();

        $query = $model->newQuery();

        // We will ensure our object GUID attribute is always selected
        // along will all attributes. Otherwise, if the object GUID
        // attribute is virtual, it may not be returned.
        $selects = array_unique(array_merge(['*', $model->getGuidKey()], $query->getSelects()));

        $query->select($selects);

        /** @var ScopeInterface $scope */
        foreach ($this->domain->scopes() as $scope) {
            $scope->apply($query);
        }

        return $query;
    }
}
