<?php

namespace LdapRecord\Laravel;

use LdapRecord\LdapRecordException;
use LdapRecord\Models\Model;

class LdapImportException extends LdapRecordException
{
    /**
     * Generate a new exception for a model that is missing a GUID.
     *
     * @param Model $model
     *
     * @return static
     */
    public static function missingGuid(Model $model)
    {
        $class =  get_class($model);

        return new static(
            "Attribute [{$model->getGuidKey()}] does not exist on LDAP model [{$class}] object [{$model->getDn()}]"
        );
    }
}
