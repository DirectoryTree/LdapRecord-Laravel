<?php

namespace LdapRecord\Laravel\Import\Hydrators;

use Closure;
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
            if (! $this->isAttributeHandler($ldapField)) {
                $eloquent->{$eloquentField} = is_string($ldapField)
                    ? $object->getFirstAttribute($ldapField)
                    : $ldapField;

                continue;
            }

            if ($ldapField instanceof Closure) {
                $ldapField($object, $eloquent);

                continue;
            }

            if (is_callable($handler = app($ldapField))) {
                $handler($object, $eloquent);

                continue;
            }

            $handler->handle($object, $eloquent);
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
            ['name' => 'cn', 'email' => 'mail']
        );
    }

    /**
     * Determines if the given value is an attribute handler.
     */
    protected function isAttributeHandler(mixed $value): bool
    {
        if ($value instanceof Closure) {
            return true;
        }

        return class_exists($value) && (method_exists($value, '__invoke') || method_exists($value, 'handle'));
    }
}
