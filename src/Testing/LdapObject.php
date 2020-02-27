<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;

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
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function attributes()
    {
        return $this->hasMany(LdapObjectAttribute::class);
    }
}
