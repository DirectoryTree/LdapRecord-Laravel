<?php

namespace LdapRecord\Laravel\Testing;

class VirtualAttributeValueObserver
{
    /**
     * The attributes to watch with their related attributes to update.
     */
    public static array $attributes = [
        'member' => 'memberof',
    ];

    /**
     * Handle updating the virtual attribute in the related model(s).
     */
    public function saved(LdapObjectAttributeValue $model): void
    {
        if (! $this->isWatchedAttribute($model->attribute->name)) {
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
     * Handle deleting the virtual attribute in the related model(s).
     */
    public function deleted(LdapObjectAttributeValue $model): void
    {
        if (! $this->isWatchedAttribute($model->attribute->name)) {
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
     * Determine if the attribute is a watched attribute.
     */
    protected function isWatchedAttribute(string $attribute): bool
    {
        return array_key_exists($attribute, static::$attributes);
    }
}
