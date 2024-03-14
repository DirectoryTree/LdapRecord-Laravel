<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Builder;

class VirtualAttributeObserver
{
    /**
     * The attributes to watch with their related attributes to update.
     */
    public static array $attributes = [
        'dn' => ['member', 'memberof'],
    ];

    /**
     * Handle updating the virtual attribute values in related model(s).
     */
    public function updated(LdapObject $model): void
    {
        if (empty($changes = $model->getChanges())) {
            return;
        }

        foreach (static::$attributes as $property => $attributes) {
            if (! array_key_exists($property, $changes)) {
                continue;
            }

            LdapObjectAttributeValue::query()
                ->where('value', $model->getOriginal($property))
                ->whereHas('attribute', fn (Builder $query) => $query->whereIn('name', $attributes))
                ->each(fn (LdapObjectAttributeValue $value) => $value->update(['value' => $changes[$property]]));
        }
    }
}
