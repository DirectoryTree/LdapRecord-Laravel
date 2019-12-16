<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Foundation\Auth\User;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;

class TestUser extends User implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;
}
