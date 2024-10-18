<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\HasLdapUser;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class TestUserModelStub extends User implements LdapAuthenticatable
{
    use AuthenticatesWithLdap, HasLdapUser, SoftDeletes;

    protected $guarded = [];

    protected $table = 'users';
}
