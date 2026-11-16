<?php

namespace LdapRecord\Laravel\Tests\Unit;

use LdapRecord\Laravel\Middleware\UserDomainValidator;
use LdapRecord\Models\ActiveDirectory\User;
use PHPUnit\Framework\TestCase;

class UserDomainValidatorTest extends TestCase
{
    public function test_domain_matches_only_the_first_domain_component()
    {
        $user = new User;
        $user->setDn('cn=alice,ou=users,dc=evil,dc=example,dc=com');

        $validator = new UserDomainValidator;

        $this->assertTrue($validator($user, 'alice', 'evil'));
        $this->assertTrue($validator($user, 'alice', 'EVIL'));
        $this->assertFalse($validator($user, 'alice', 'example'));
        $this->assertFalse($validator($user, 'alice', 'com'));
    }

    public function test_parent_domain_user_does_not_match_child_domain()
    {
        $user = new User;
        $user->setDn('cn=alice,ou=users,dc=example,dc=com');

        $validator = new UserDomainValidator;

        $this->assertTrue($validator($user, 'alice', 'EXAMPLE'));
        $this->assertFalse($validator($user, 'alice', 'evil'));
    }

    public function test_domain_is_required()
    {
        $user = new User;
        $user->setDn('cn=alice,dc=example,dc=com');

        $validator = new UserDomainValidator;

        $this->assertFalse($validator($user, 'alice'));
        $this->assertFalse($validator($user, 'alice', ''));
    }

    public function test_distinguished_name_requires_domain_components()
    {
        $user = new User;
        $user->setDn('cn=alice,ou=users,o=example');

        $this->assertFalse((new UserDomainValidator)($user, 'alice', 'example'));
    }
}
