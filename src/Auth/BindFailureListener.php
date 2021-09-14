<?php

namespace LdapRecord\Laravel\Auth;

use Closure;

class BindFailureListener
{
    use ListensForLdapBindFailure;

    /**
     * Register the bind failure listener for Laravel Jetstream.
     *
     * @param string $request
     *
     * @return void
     */
    public static function usingLaravelJetstream($request = 'Laravel\Fortify\Http\Requests\LoginRequest')
    {
        static::whenResolving($request);
    }

    /**
     * Register the bind failure listener for Laravel UI.
     *
     * @param string $controller
     *
     * @return void
     */
    public static function usingLaravelUi($controller = 'App\Http\Controllers\Auth\LoginController')
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
     *
     * @param string       $class
     * @param Closure|null $callback
     *
     * @return void
     */
    protected static function whenResolving($class, Closure $callback = null)
    {
        if (! class_exists($class)) {
            return;
        }

        app()->resolving($class, $callback ?? function () {
            (new static)->listenForLdapBindFailure();
        });
    }
}
