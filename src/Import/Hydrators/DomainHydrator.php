<?php

namespace LdapRecord\Laravel\Import\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class DomainHydrator extends Hydrator
{
    /**
     * @inheritdoc
     */
    public function hydrate(LdapModel $object, EloquentModel $eloquent)
    {
        $eloquent->setLdapDomain($object->getConnectionName() ?? config('ldap.default'));
    }
}
