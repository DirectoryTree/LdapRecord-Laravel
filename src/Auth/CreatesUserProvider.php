<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;

trait CreatesUserProvider
{
    /**
     * Attempt to retrieve the authenticated guard name.
     *
     * @return string|null
     */
    protected function getCurrentAuthGuard()
    {
        $guard = $this->resolveAuthenticatedGuard(
            array_keys(config('auth.guards', []))
        );

        switch ($guard) {
            case 'sanctum':
                return $this->resolveAuthenticatedGuard(
                    Arr::wrap(config('sanctum.guard'))
                );
            default:
                return $guard;
        }
    }

    /**
     * Resolve the authenticated guard from the given array of guards.
     *
     * @param array $guards
     *
     * @return string|null
     */
    protected function resolveAuthenticatedGuard($guards = [])
    {
        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }
    }

    /**
     * Get the guard's authentication user provider.
     *
     * @param string $guard
     *
     * @return \Illuminate\Contracts\Auth\UserProvider|null
     */
    protected function getCurrentAuthProvider($guard)
    {
        return Auth::createUserProvider(
            config("auth.guards.$guard.provider")
        );
    }
}
