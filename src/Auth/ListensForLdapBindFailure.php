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
                $this->handleLdapBindError($errorMessage);
                break;
            case $this->causedByInvalidCredentials($errorMessage, $diagnosticMessage):
                // We'll bypass any invalid LDAP credential errors and let
                // the login controller handle it. This is so proper
                // translation can be done on the validation error.
                break;
            default:
                foreach ($this->ldapDiagnosticCodeErrorMap() as $code => $message) {
                    if ($this->errorContainsMessage($diagnosticMessage, (string) $code)) {
                        $this->handleLdapBindError($message, $code);
                    }
                }
        }
    }

    /**
     * Handle the LDAP bind error.
     *
     * @param string $message
     * @param string $code
     *
     * @throws ValidationException
     */
    protected function handleLdapBindError($message, $code = null)
    {
        $this->throwLoginValidationException($message);
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
     * @param string $diagnosticMessage
     *
     * @return bool
     */
    protected function causedByInvalidCredentials($errorMessage, $diagnosticMessage)
    {
        return
            $this->errorContainsMessage($errorMessage, 'Invalid credentials') &&
            $this->errorContainsMessage($diagnosticMessage, '52e');
    }

    /**
     * The LDAP diagnostic code error map.
     *
     * @return array
     */
    protected function ldapDiagnosticCodeErrorMap()
    {
        return [
            '525' => trans('ldap::errors.user_not_found'),
            '530' => trans('ldap::errors.user_not_permitted_at_this_time'),
            '531' => trans('ldap::errors.user_not_permitted_to_login'),
            '532' => trans('ldap::errors.password_expired'),
            '533' => trans('ldap::errors.account_disabled'),
            '701' => trans('ldap::errors.account_expired'),
            '773' => trans('ldap::errors.user_must_reset_password'),
            '775' => trans('ldap::errors.user_account_locked'),
        ];
    }
}
