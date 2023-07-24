<?php

namespace LdapRecord\Laravel\Auth;

use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;

trait CreatesUserProvider
{
    /**
     * Attempt to retrieve the authenticated guard name.
     */
    protected function getCurrentAuthGuard(): ?string
    {
        foreach (Config::get('auth.guards') as $guard => $config) {
            if (Auth::guard($guard)->check()) {
                return $guard;
            }
        }

        return null;
    }

    /**
     * Get the guard's authentication user provider.
     */
    protected function getCurrentAuthProvider(string $guard): ?UserProvider
    {
        if ($guard === 'sanctum') {
            $guard = Arr::first(
                Arr::wrap(Config::get('sanctum.guard', 'web'))
            );
        }

        return Auth::createUserProvider(
            Config::get("auth.guards.$guard.provider")
        );
    }
}
