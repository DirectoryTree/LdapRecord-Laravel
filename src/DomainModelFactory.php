<?php

namespace LdapRecord\Laravel;

use LdapRecord\Models\Model;

class DomainModelFactory
{
    /**
     * Create a new LDAP domain model.
     *
     * @param Domain $domain
     *
     * @return Model
     */
    public static function createForDomain(Domain $domain) : Model
    {
        $modelClass = $domain->getLdapModel();

        /** @var \LdapRecord\Models\Model $model */
        $model = new $modelClass;
        $model->setConnection($domain->getName());

        return $model;
    }
}
