<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;

class LdapObjectAttribute extends Model
{
    protected $guarded = [];

    protected $with = ['values'];

    public $timestamps = false;

    protected static function boot()
    {
        parent::boot();

        static::deleted(function ($model) {
            // Delete the attribute values when deleted.
            $model->values()->delete();
        });
    }

    public function values()
    {
        return $this->hasMany(LdapObjectAttributeValue::class);
    }
}
