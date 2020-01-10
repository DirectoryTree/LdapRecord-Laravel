<?php

namespace LdapRecord\Laravel\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class DomainHydrator extends Hydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate(LdapModel $user, EloquentModel $database)
    {
        $database->setLdapDomain($user->getConnectionName() ?? config('ldap.default'));
    }
}
