<?php

namespace LdapRecord\Laravel\Auth;

use Closure;
use LdapRecord\Container;
use LdapRecord\DetectsErrors;
use LdapRecord\Auth\Events\Failed;
use Illuminate\Validation\ValidationException;

trait ListensForLdapBindFailure
{
    use DetectsErrors;

    /**
     * The bind error handler callback.
     *
     * @var Closure|null
     */
    protected static $bindErrorHandler;

    /**
     * Set the bind error handler callback.
     *
     * @param Closure $callback
     *
     * @return void
     */
    public static function setErrorHandler(Closure $callback)
    {
        static::$bindErrorHandler = $callback;
    }

    /**
     * Setup a listener for an LDAP bind failure.
     *
     * @return void
     */
    public function listenForLdapBindFailure()
    {
        Container::getInstance()->getEventDispatcher()->listen(Failed::class, function (Failed $event) {
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
     * @return void
     *
     * @throws ValidationException
     */
    protected function ldapBindFailed($errorMessage, $diagnosticMessage = null)
    {
        switch (true) {
            case $this->causedByLostConnection($errorMessage):
                return $this->handleLdapBindError($errorMessage);
            case $this->causedByInvalidCredentials($errorMessage, $diagnosticMessage):
                // We'll bypass any invalid LDAP credential errors and let
                // the login controller handle it. This is so proper
                // translation can be done on the validation error.
                return;
            default:
                foreach ($this->ldapDiagnosticCodeErrorMap() as $code => $message) {
                    if ($this->errorContainsMessage($diagnosticMessage, (string) $code)) {
                        return $this->handleLdapBindError($message, $code);
                    }
                }
        }
    }

    /**
     * Handle the LDAP bind error.
     *
     * @param string      $message
     * @param string|null $code
     *
     * @return void
     *
     * @throws ValidationException
     */
    protected function handleLdapBindError($message, $code = null)
    {
        ($callback = static::$bindErrorHandler)
            ? $callback($message, $code)
            : $this->throwLoginValidationException($message);
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
        if (class_exists($fortify = 'Laravel\Fortify\Fortify')) {
            $username = $fortify::username();
        }

        throw ValidationException::withMessages([
            $username ?? $this->username() => $message,
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
        return $this->errorContainsMessage($errorMessage, 'Invalid credentials')
            && $this->errorContainsMessage($diagnosticMessage, '52e');
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
