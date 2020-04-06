<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Auth\Rules\OnlyImported;
use LdapRecord\Models\Entry;

class ValidatorRuleTest extends TestCase
{
    use CreatesTestUsers;

    public function test_only_imported()
    {
        $this->assertFalse((new OnlyImported(new Entry, new TestUserModelStub))->isValid());

        $user = $this->createTestUser([
            'name' => 'John Doe',
            'email' => 'jdoe@email.com',
            'password' => 'password',
            'deleted_at' => now(),
        ]);

        $this->assertTrue((new OnlyImported(new Entry, $user))->isValid());
    }
}
