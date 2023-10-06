<?php

namespace LdapRecord\Laravel\Testing;

class VirtualAttributeValueObserver
{
    /**
     * The virtual attributes to update when changed.
     */
    public static array $attributes = [
        'member' => 'memberof',
    ];

    /**
     * Handle updating the virtual attribute in the related model.
     */
    public function saving(LdapObjectAttributeValue $model): void
    {
        if (! $this->hasVirtualAttribute($model->attribute->name)) {
            return;
        }

        /** @var LdapObject|null $object */
        if (! $object = LdapObject::findByDn($model->value)) {
            return;
        }

        /** @var LdapObjectAttribute $attribute */
        $attribute = $object->attributes()->firstOrCreate([
            'name' => static::$attributes[$model->attribute->name],
        ]);

        $attribute->values()->firstOrCreate([
            'value' => $model->attribute->object->dn,
        ]);
    }

    /**
     * Handle deleting the virtual attribute in the related model.
     */
    public function deleting(LdapObjectAttributeValue $model): void
    {
        if (! $this->hasVirtualAttribute($model->attribute->name)) {
            return;
        }

        /** @var LdapObject|null $object */
        if (! $object = LdapObject::findByDn($model->value)) {
            return;
        }

        /** @var LdapObjectAttribute|null $attribute */
        if (! $attribute = $object->attributes()->firstWhere('name', static::$attributes[$model->attribute->name])) {
            return;
        }

        $attribute->values()->where('value', $model->attribute->object->dn)->delete();
    }

    /**
     * Determine if a virtual attribute exists for the given attribute.
     */
    protected function hasVirtualAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, static::$attributes);
    }
}
