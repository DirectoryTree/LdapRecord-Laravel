<?php

namespace LdapRecord\Laravel\Import;

use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model;

class ImportException extends LdapRecordException
{
    /**
     * Generate a new exception for a model that is missing a GUID.
     */
    public static function missingGuid(Model $model): static
    {
        return new static(
            sprintf(
                'Attribute [%s] does not exist on LDAP model [%s] object [%s]',
                $model->getGuidKey(),
                get_class($model),
                $model->getDn()
            )
        );
    }
}
