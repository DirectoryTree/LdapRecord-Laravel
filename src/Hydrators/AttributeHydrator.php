<?php

namespace LdapRecord\Laravel\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use LdapRecord\Models\Model as LdapModel;

class AttributeHydrator extends Hydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate(LdapModel $user, EloquentModel $database)
    {
        foreach ($this->getSyncAttributes() as $modelField => $ldapField) {
            // If the field is a loaded class and contains a `handle()`
            // method, we need to construct the attribute handler.
            if ($this->isAttributeHandler($ldapField)) {
                // We will construct the attribute handler using Laravel's
                // IoC to allow developers to utilize application
                // dependencies in the constructor.
                app($ldapField)->handle($user, $database);
            } else {
                // We'll try to retrieve the value from the LDAP model. If the LDAP field is
                // a string, we'll assume the developer wants the attribute, or a null
                // value. Otherwise, the raw value of the LDAP field will be used.
                $database->{$modelField} = is_string($ldapField) ? $user->getFirstAttribute($ldapField) : $ldapField;
            }
        }
    }

    /**
     * Get the database sync attributes.
     *
     * @return array
     */
    protected function getSyncAttributes()
    {
        return (array) Arr::get($this->config, 'sync_attributes', ['name' => 'cn', 'email' => 'mail']);
    }

    /**
     * Determines if the given handler value is a class that contains the 'handle' method.
     *
     * @param mixed $handler
     *
     * @return bool
     */
    protected function isAttributeHandler($handler)
    {
        return is_string($handler) && class_exists($handler) && method_exists($handler, 'handle');
    }
}
