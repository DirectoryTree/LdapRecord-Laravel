<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Laravel\Sanctum\HasApiTokens;
use LdapRecord\Laravel\Tests\Feature\TestUserModelStub;

class SanctumTestUserModelStub extends TestUserModelStub
{
    use HasApiTokens;
}
