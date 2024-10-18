<?php

namespace LdapRecord\Laravel\Auth;

use Closure;

class BindFailureListener
{
    use ListensForLdapBindFailure;

    /**
     * Register the bind failure listener for Laravel Jetstream.
     */
    public static function usingLaravelJetstream(string $request = 'Laravel\Fortify\Http\Requests\LoginRequest'): void
    {
        static::whenResolving($request);
    }

    /**
     * Register the bind failure listener for Laravel UI.
     */
    public static function usingLaravelUi(string $controller = 'App\Http\Controllers\Auth\LoginController'): void
    {
        static::whenResolving($controller, function ($controller) {
            $traits = class_uses_recursive($controller);

            if (in_array(ListensForLdapBindFailure::class, $traits)) {
                $controller->listenForLdapBindFailure();
            }
        });
    }

    /**
     * Register the bind failure listener upon resolving the given class.
     */
    protected static function whenResolving(string $class, ?Closure $callback = null): void
    {
        if (! class_exists($class)) {
            return;
        }

        app()->resolving($class, $callback ?? function () {
            (new static)->listenForLdapBindFailure();
        });
    }
}
