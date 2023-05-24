<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LdapObjectAttribute extends Model
{
    use ResolvesEmulatedConnection;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The relations to eager load on every query.
     *
     * @var array
     */
    protected $with = ['values'];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The "boot" method of the model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::deleted(function ($model) {
            // Delete the attribute values when deleted.
            $model->values()->delete();
        });
    }

    /**
     * The hasMany values relation.
     */
    public function values(): HasMany
    {
        return $this->hasMany(LdapObjectAttributeValue::class);
    }
}
