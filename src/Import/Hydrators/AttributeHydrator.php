<?php

namespace LdapRecord\Laravel\Import\Hydrators;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Arr;
use LdapRecord\Models\Model as LdapModel;

class AttributeHydrator extends Hydrator
{
    /**
     * {@inheritdoc}
     */
    public function hydrate(LdapModel $object, EloquentModel $eloquent): void
    {
        foreach ($this->getSyncAttributes() as $eloquentField => $ldapField) {
            if ($this->isAttributeHandler($ldapField)) {
                app($ldapField)->handle($object, $eloquent);

                continue;
            }

            $eloquent->{$eloquentField} = is_string($ldapField)
                ? $object->getFirstAttribute($ldapField)
                : $ldapField;
        }
    }

    /**
     * Get the database sync attributes.
     */
    protected function getSyncAttributes(): array
    {
        return (array) Arr::get(
            $this->config,
            'sync_attributes',
            $default = ['name' => 'cn', 'email' => 'mail']
        );
    }

    /**
     * Determines if the given handler value is a class that contains the 'handle' method.
     */
    protected function isAttributeHandler($handler): bool
    {
        return is_string($handler) && class_exists($handler) && method_exists($handler, 'handle');
    }
}
