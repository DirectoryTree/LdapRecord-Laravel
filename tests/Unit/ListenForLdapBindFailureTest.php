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

    protected function directoryThrowsValidationErrorWithCode($code)
    {
        $fake = DirectoryEmulator::setup('default');

        /** @var \LdapRecord\Testing\LdapFake $ldap */
        $ldap = $fake->getLdapConnection();
        $ldap->shouldReturnDiagnosticMessage($this->makeDiagnosticErrorMessage($code));

        $this->expectException(ValidationException::class);

        $fake->auth()->attempt('user', 'secret');
    }

    public function test_validation_exception_is_thrown_on_user_not_found()
    {
        $this->directoryThrowsValidationErrorWithCode('525');
    }

    public function test_validation_exception_when_user_is_not_permitted_to_login()
    {
        $this->directoryThrowsValidationErrorWithCode('530');
    }

    public function test_validation_exception_when_user_is_not_permitted_to_login_to_workstation()
    {
        $this->directoryThrowsValidationErrorWithCode('531');
    }

    public function test_validation_exception_when_users_password_has_expired()
    {
        $this->directoryThrowsValidationErrorWithCode('532');
    }

    public function test_validation_exception_when_users_account_is_disabled()
    {
        $this->directoryThrowsValidationErrorWithCode('533');
    }

    public function test_validation_exception_when_users_account_has_expired()
    {
        $this->directoryThrowsValidationErrorWithCode('701');
    }

    public function test_validation_exception_when_users_account_must_reset_password()
    {
        $this->directoryThrowsValidationErrorWithCode('773');
    }

    public function test_validation_exception_when_users_account_is_locked_out()
    {
        $this->directoryThrowsValidationErrorWithCode('775');
    }
}
