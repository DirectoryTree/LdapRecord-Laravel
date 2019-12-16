<?php

namespace LdapRecord\Laravel\Database;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Domain;
use LdapRecord\Models\Model as LdapModel;

class UserImportScope implements Scope
{
    /**
     * The LDAP domain that the user belongs to.
     *
     * @var Domain
     */
    protected $domain;

    /**
     * The LDAP user being located for import.
     *
     * @var LdapModel
     */
    protected $user;

    /**
     * Constructor.
     *
     * @param Domain    $domain
     * @param LdapModel $user
     */
    public function __construct(Domain $domain, LdapModel $user)
    {
        $this->domain = $domain;
        $this->user = $user;
    }

    /**
     * Apply the scope to a given Eloquent query builder.
     *
     * @param Builder $query
     * @param Model   $model
     *
     * @return void
     */
    public function apply(Builder $query, Model $model)
    {
        $this->user($query, $model);
    }

    /**
     * Applies the user scope to the given Eloquent query builder.
     *
     * @param Builder             $query
     * @param LdapAuthenticatable $model
     */
    protected function user(Builder $query, LdapAuthenticatable $model)
    {
        // We'll try to locate the user by their object guid,
        // otherwise we'll locate them by their username.
        $query
            ->where($model->getLdapGuidColumn(), '=', $this->getUserGuid())
            ->orWhere($this->domain->getDatabaseUsernameColumn(), '=', $this->getUserUsername());
    }

    /**
     * Returns the LDAP users object guid.
     *
     * @return string
     */
    protected function getUserGuid()
    {
        return $this->user->getObjectGuid();
    }

    /**
     * Returns the LDAP users username.
     *
     * @return string
     */
    protected function getUserUsername()
    {
        return $this->user->getFirstAttribute($this->domain->getDatabaseUsernameColumn());
    }
}
