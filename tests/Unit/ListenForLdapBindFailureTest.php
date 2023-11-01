<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Validation\ValidationException;
use LdapRecord\Laravel\Auth\ListensForLdapBindFailure;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\TestCase;

class ListenForLdapBindFailureTest extends TestCase
{
    use ListensForLdapBindFailure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->listenForLdapBindFailure();
    }

    protected function username()
    {
        return 'email';
    }

    protected function makeDiagnosticErrorMessage($code)
    {
        return "80090308: LdapErr: DSID-0C09030B, comment: AcceptSecurityContext error, data {$code}, v893";
    }

    public function test_validation_exception_is_not_thrown_when_no_error_is_given()
    {
        $fake = DirectoryEmulator::setup('default');

        $this->assertFalse($fake->auth()->attempt('user', 'secret'));
    }

    public function test_validation_exception_is_not_thrown_when_invalid_credentials_is_returned()
    {
        $fake = DirectoryEmulator::setup('default');

        /** @var \LdapRecord\Testing\LdapFake $ldap */
        $ldap = $fake->getLdapConnection();
        $ldap->shouldReturnDiagnosticMessage(null);
        $ldap->shouldReturnError('Invalid credentials');

        $this->assertFalse($fake->auth()->attempt('user', 'secret'));
    }

    public function test_validation_exception_is_thrown_on_lost_connection()
    {
        $fake = DirectoryEmulator::setup('default');

        /** @var \LdapRecord\Testing\LdapFake $ldap */
        $ldap = $fake->getLdapConnection();
        $ldap->shouldReturnError("Can't contact LDAP server");

        $this->expectException(ValidationException::class);

        $fake->auth()->attempt('user', 'secret');
    }

    /**
     * @testWith
     * ["525"]
     * ["530"]
     * ["531"]
     * ["532"]
     * ["533"]
     * ["701"]
     * ["773"]
     * ["775"]
     */
    public function test_directory_throws_validation_error_with_code($code)
    {
        $fake = DirectoryEmulator::setup('default');

        /** @var \LdapRecord\Testing\LdapFake $ldap */
        $ldap = $fake->getLdapConnection();
        $ldap->shouldReturnDiagnosticMessage($this->makeDiagnosticErrorMessage($code));

        $this->expectException(ValidationException::class);

        $fake->auth()->attempt('user', 'secret');
    }
}
