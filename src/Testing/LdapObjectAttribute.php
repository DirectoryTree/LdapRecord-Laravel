<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;

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
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($model) {
            // Delete the attribute values when deleted.
            $model->values()->delete();
        });
    }

    /**
     * The hasMany values relation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function values()
    {
        return $this->hasMany(LdapObjectAttributeValue::class);
    }
}
