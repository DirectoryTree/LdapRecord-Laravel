<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Laravel\Sanctum\HasApiTokens;

class SanctumTestUserModelStub extends TestUserModelStub
{
    use HasApiTokens;
}
