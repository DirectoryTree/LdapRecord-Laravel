<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $ldap_object_attribute_id
 * @property string $value
 * @property LdapObjectAttribute $attribute
 */
class LdapObjectAttributeValue extends Model
{
    use ResolvesEmulatedConnection;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Register the virtual attribute observer.
     */
    protected static function booted(): void
    {
        static::observe(VirtualAttributeValueObserver::class);
    }

    /**
     * The attribute relationship.
     */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(LdapObjectAttribute::class, 'ldap_object_attribute_id');
    }
}
