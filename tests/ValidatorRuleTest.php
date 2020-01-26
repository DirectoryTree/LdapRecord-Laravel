<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Models\Entry;
use LdapRecord\Laravel\Auth\Rules\DenyTrashed;
use LdapRecord\Laravel\Auth\Rules\OnlyImported;

class ValidatorRuleTest extends TestCase
{
    use CreatesTestUsers;

    public function test_deny_trashed()
    {
        $user = $this->createTestUser([
            'name' => 'John Doe',
            'email' => 'jdoe@email.com',
            'password' => 'password',
            'deleted_at' => now(),
        ]);

        $this->assertFalse((new DenyTrashed(new Entry, $user))->isValid());
    }

    public function test_only_imported()
    {
        $this->assertFalse((new OnlyImported(new Entry, new TestUser))->isValid());

        $user = $this->createTestUser([
            'name' => 'John Doe',
            'email' => 'jdoe@email.com',
            'password' => 'password',
            'deleted_at' => now(),
        ]);

        $this->assertTrue((new OnlyImported(new Entry, $user))->isValid());
    }
}
