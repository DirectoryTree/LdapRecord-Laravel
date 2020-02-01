<?php

namespace LdapRecord\Laravel\Testing;

use Illuminate\Database\Eloquent\Model;

class LdapObject extends Model
{
    protected $guarded = [];

    public function attributes()
    {
        return $this->hasMany(LdapObjectAttribute::class, 'ldap_object_id');
    }

    public function classes()
    {
        return $this->hasMany(LdapObjectClass::class, 'ldap_object_id');
    }
}
