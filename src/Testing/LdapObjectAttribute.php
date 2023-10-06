<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $ldap_object_id
 * @property string $name
 * @property LdapObject $object
 * @property \Illuminate\Database\Eloquent\Collection $values
 */
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

        static::deleting(function (self $model) {
            // Delete the attribute values when deleted.
            $model->values()->each(
                fn (LdapObjectAttributeValue $value) => $value->delete()
            );
        });
    }

    /**
     * The object relationship.
     */
    public function object(): BelongsTo
    {
        return $this->belongsTo(LdapObject::class, 'ldap_object_id');
    }

    /**
     * The values relationship.
     */
    public function values(): HasMany
    {
        return $this->hasMany(LdapObjectAttributeValue::class);
    }
}
