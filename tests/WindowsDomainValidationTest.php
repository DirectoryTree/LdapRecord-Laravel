<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Middleware\WindowsAuthenticate;
use LdapRecord\Models\ActiveDirectory\User;
use PHPUnit\Framework\TestCase;

class WindowsDomainValidationTest extends TestCase
{
    public function test_domain_matches_only_the_first_domain_component()
    {
        $user = new User;
        $user->setDn('cn=alice,ou=users,dc=evil,dc=example,dc=com');

        $middleware = new WindowsDomainValidationMiddleware;

        $this->assertTrue($middleware->validateDomain($user, 'evil'));
        $this->assertTrue($middleware->validateDomain($user, 'EVIL'));
        $this->assertFalse($middleware->validateDomain($user, 'example'));
        $this->assertFalse($middleware->validateDomain($user, 'com'));
        $this->assertFalse($middleware->validateDomain($user, 'vil'));
    }

    public function test_parent_domain_user_does_not_match_child_domain()
    {
        $user = new User;
        $user->setDn('cn=alice,ou=users,dc=example,dc=com');

        $middleware = new WindowsDomainValidationMiddleware;

        $this->assertTrue($middleware->validateDomain($user, 'EXAMPLE'));
        $this->assertFalse($middleware->validateDomain($user, 'evil'));
    }

    public function test_domain_and_domain_components_are_required()
    {
        $user = new User;
        $user->setDn('cn=alice,dc=example,dc=com');

        $middleware = new WindowsDomainValidationMiddleware;

        $this->assertFalse($middleware->validateDomain($user, ''));

        $user->setDn('cn=alice,ou=users,o=example');

        $this->assertFalse($middleware->validateDomain($user, 'example'));
    }
}

class WindowsDomainValidationMiddleware extends WindowsAuthenticate
{
    public function __construct()
    {
        static::$domainVerification = true;
    }

    public function validateDomain(User $user, $domain)
    {
        return $this->userIsApartOfDomain($user, $domain);
    }
}
