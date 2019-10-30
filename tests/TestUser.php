<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Foundation\Auth\User;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;

class TestUser extends User implements LdapAuthenticatable
{
    use AuthenticatesWithLdap;
}
