<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Builder;

class VirtualObjectObserver
{
    /**
     * The virtual attributes to observe.
     */
    protected array $attributes = [
        'dn' => [
            'member',
            'memberof',
        ],
    ];

    /**
     * Handle updating the virtual attribute values in related models.
     */
    public function updated(LdapObject $model): void
    {
        if (empty($changes = $model->getChanges())) {
            return;
        }

        foreach ($this->attributes as $property => $attributes) {
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
