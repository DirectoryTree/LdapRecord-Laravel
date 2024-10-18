<?php

namespace LdapRecord\Laravel\Auth;

use Closure;
use Illuminate\Validation\ValidationException;
use LdapRecord\Auth\Events\Failed as BindFailed;
use LdapRecord\Container;
use LdapRecord\DetectsErrors;
use LdapRecord\Events\Connecting;

trait ListensForLdapBindFailure
{
    use DetectsErrors;

    /**
     * The bind error handler callback.
     */
    protected static ?Closure $bindErrorHandler = null;

    /**
     * Set the bind error handler callback.
     */
    public static function setErrorHandler(Closure $callback): void
    {
        static::$bindErrorHandler = $callback;
    }

    /**
     * Set up a listener for an LDAP bind failure.
     */
    public function listenForLdapBindFailure(): void
    {
        $dispatcher = Container::getDispatcher();

        $isOnLastHost = true;

        // We will set up an event listener on the connecting event to determine if there are
        // multiple LDAP hosts in use. If there are, we will make sure to wait until the
        // last LDAP host is attempted before throwing the login validation exception.
        $dispatcher->listen(Connecting::class, function (Connecting $event) use (&$isOnLastHost) {
            $connection = $event->getConnection();

            $numberOfHosts = count($connection->getConfiguration()->get('hosts'));

            $numberOfHostsAttempted = count($connection->attempted());

            $isOnLastHost = ($numberOfHosts - 1) - $numberOfHostsAttempted === 0;
        });

        $dispatcher->listen(BindFailed::class, function (BindFailed $event) use (&$isOnLastHost) {
            if (! $isOnLastHost) {
                return;
            }

            $this->ldapBindFailed(
                $event->getConnection()->getLastError(),
                $event->getConnection()->getDiagnosticMessage()
            );
        });
    }

    /**
     * Generate a human validation error for LDAP bind failures.
     *
     * @throws ValidationException
     */
    protected function ldapBindFailed(string $errorMessage, ?string $diagnosticMessage = null): void
    {
        switch (true) {
            case $this->causedByLostConnection($errorMessage):
                $this->handleLdapBindError($errorMessage);

                return;

            case is_null($diagnosticMessage):
                // If there is no diagnostic message to work with, we
                // cannot make any further attempts to determine
                // the error. We will bail here in such case.
                return;

            case $this->causedByInvalidCredentials($errorMessage, $diagnosticMessage):
                // We'll bypass any invalid LDAP credential errors and let
                // the login controller handle it. This is so proper
                // translation can be done on the validation error.
                return;
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
     * @throws ValidationException
     */
    protected function handleLdapBindError(string $message, ?string $code = null): void
    {
        logger()->error($message, compact('code'));

        ($callback = static::$bindErrorHandler)
            ? $callback($message, $code)
            : $this->throwLoginValidationException($message);
    }

    /**
     * Throw a login validation exception.
     *
     * @throws ValidationException
     */
    protected function throwLoginValidationException(string $message): void
    {
        $username = 'email';

        if (class_exists($fortify = 'Laravel\Fortify\Fortify')) {
            $username = $fortify::username();
        } elseif (method_exists($this, 'username')) {
            $username = $this->username();
        } elseif (property_exists($this, 'username')) {
            $username = $this->username;
        }

        throw ValidationException::withMessages([
            $username => $message,
        ]);
    }

    /**
     * Determine if the LDAP error generated is caused by invalid credentials.
     */
    protected function causedByInvalidCredentials(string $errorMessage, string $diagnosticMessage): bool
    {
        return $this->errorContainsMessage($errorMessage, 'Invalid credentials')
            && $this->errorContainsMessage($diagnosticMessage, '52e');
    }

    /**
     * The LDAP diagnostic code error map.
     */
    protected function ldapDiagnosticCodeErrorMap(): array
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
