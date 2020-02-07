<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class TestUser extends User implements LdapAuthenticatable
{
    use SoftDeletes, AuthenticatesWithLdap;

    protected $guarded = [];

    protected $table = 'users';
}
