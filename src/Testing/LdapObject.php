<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;

class LdapObject extends Model
{
    protected $guarded = [];

    protected $with = ['attributes'];

    public function attributes()
    {
        return $this->hasMany(LdapObjectAttribute::class);
    }
}
