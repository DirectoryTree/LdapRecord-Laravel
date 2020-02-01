<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;

class LdapObjectAttribute extends Model
{
    protected $guarded = [];

    protected $casts = ['values' => 'array'];

    public $timestamps = false;
}
