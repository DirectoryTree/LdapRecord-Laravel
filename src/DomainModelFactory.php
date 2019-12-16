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

        return tap(new $modelClass, function (Model $model) use ($domain) {
            $model->setConnection($domain->getName());
        });
    }
}
