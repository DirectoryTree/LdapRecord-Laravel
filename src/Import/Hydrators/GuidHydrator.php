<?php

namespace LdapRecord\Laravel\Import\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use LdapRecord\Models\Model as LdapModel;

class GuidHydrator extends Hydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate(LdapModel $object, EloquentModel $eloquent): void
    {
        $eloquent->setLdapGuid($object->getConvertedGuid());
    }
}
