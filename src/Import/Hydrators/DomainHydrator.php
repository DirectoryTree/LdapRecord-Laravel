<?php

namespace LdapRecord\Laravel\Import\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Config;
use LdapRecord\Models\Model as LdapModel;

class DomainHydrator extends Hydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate(LdapModel $object, EloquentModel $eloquent): void
    {
        $eloquent->setLdapDomain($object->getConnectionName() ?? Config::get('ldap.default'));
    }
}
