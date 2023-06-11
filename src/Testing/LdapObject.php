<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
