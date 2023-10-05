<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $guid
 * @property string $guid_key
 * @property string $dn
 * @property string $name
 * @property string $domain
 * @property string $parent_dn
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Illuminate\Database\Eloquent\Collection $attributes
 */
class LdapObject extends Model
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
    protected $with = ['attributes'];

    /**
     * The hasMany attributes relation.
     */
    public function attributes(): HasMany
    {
        return $this->hasMany(LdapObjectAttribute::class);
    }
}
