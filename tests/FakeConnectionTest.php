<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Auth\BindException;
use LdapRecord\Container;
use LdapRecord\Laravel\Testing\FakeDirectory;
use LdapRecord\Models\ActiveDirectory\User;

class FakeConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        FakeDirectory::setup('default');

        $this->setupDatabaseUserProvider();
    }

    public function test_auth_fails()
    {
        $conn = Container::getConnection('default');
        $this->expectException(BindException::class);
        $conn->auth()->attempt('user', 'pass');
    }

    public function test_auth_passes()
    {
        $conn = Container::getConnection('default');
        $conn->actingAs(User::create(['cn' => 'John']));
        $this->assertTrue($conn->auth()->attempt('user', 'pass'));
    }
}
