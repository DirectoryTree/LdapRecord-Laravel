<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Validation\ValidationException;
use LdapRecord\Auth\Events\Failed;
use LdapRecord\Container;
use LdapRecord\DetectsErrors;

trait ListensForLdapBindFailure
{
    use DetectsErrors;

    /**
     * Setup a listener for an LDAP bind failure.
     */
    public function listenForLdapBindFailure()
    {
        Container::getEventDispatcher()->listen(Failed::class, function (Failed $event) {
            $error = $event->getConnection()->getDetailedError();

            $this->ldapBindFailed($error->getErrorMessage(), $error->getDiagnosticMessage());
        });
    }

    /**
     * Generate a human validation error for LDAP bind failures.
     *
     * @param string      $errorMessage
     * @param string|null $diagnosticMessage
     *
     * @throws ValidationException
     */
    protected function ldapBindFailed($errorMessage, $diagnosticMessage = null)
    {
        switch (true) {
            case $this->causedByLostConnection($errorMessage):
                $this->throwLoginValidationException($errorMessage);
                break;
            case $this->causedByInvalidCredentials($errorMessage);
                // We'll bypass any invalid LDAP credential errors and let
                // the login controller handle it. This is so proper
                // translation can be done on the validation error.
                break;
            default:
                foreach ($this->ldapDiagnosticCodeErrorMap() as $code => $message) {
                    if ($this->errorContainsMessage($diagnosticMessage, $code)) {
                        $this->throwLoginValidationException($message);
                    }
                }
        }
    }

    /**
     * Throw a login validation exception.
     *
     * @param string $message
     *
     * @throws ValidationException
     */
    protected function throwLoginValidationException($message)
    {
        throw ValidationException::withMessages([
            $this->username() => $message,
        ]);
    }

    /**
     * Determine if the LDAP error generated is caused by invalid credentials.
     *
     * @param string $errorMessage
     *
     * @return bool
     */
    protected function causedByInvalidCredentials($errorMessage)
    {
        return $this->errorContainsMessage($errorMessage, "Invalid credentials");
    }

    /**
     * The LDAP diagnostic code error map.
     *
     * @return array
     */
    protected function ldapDiagnosticCodeErrorMap()
    {
        return [
            '525' => 'User not found',
            '52e' => 'Invalid credentials',
            '530' => 'Not permitted to logon at this time',
            '531' => 'Not permitted to logon at this workstation',
            '532' => 'Password expired',
            '533' => 'Account disabled',
            '701' => 'Account expired',
            '773' => 'User must reset password',
            '775' => 'User account locked',
        ];
    }
}
