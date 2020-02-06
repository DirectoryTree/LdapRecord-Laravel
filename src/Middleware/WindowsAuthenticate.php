<?php

namespace LdapRecord\Laravel\Middleware;

use Closure;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Contracts\Auth\Factory as Auth;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Models\Model;

class WindowsAuthenticate
{
    /**
     * The auth factory instance.
     *
     * @var Auth
     */
    protected $auth;

    /**
     * Constructor.
     *
     * @param Auth $auth
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure                  $next
     * @param string[]              ...$guards
     *
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, $guards);

        return $next($request);
    }

    /**
     * Attempt to authenticate the LDAP user in the given guards.
     *
     * @param \Illuminate\Http\Request $request
     * @param array                    $guards
     *
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function authenticate($request, array $guards)
    {
        if (empty($guards)) {
            $guards = [null];
        }

        foreach ($guards as $guard) {
            $provider = $this->auth->guard($guard)->getProvider();

            if ($provider instanceof UserProvider) {
                // Retrieve the users account name from the request.
                if ($account = $this->account($request)) {
                    // Retrieve the users username from their account name.
                    $username = $this->username($account);

                    // Finally, retrieve the users authenticatable model and log them in.
                    if ($user = $this->retrieveAuthenticatedUser($provider, $username)) {
                        $this->auth->shouldUse($guard);

                        return $this->auth->login($user, $remember = true);
                    }
                }
            }
        }
    }

    /**
     * Returns the authenticatable user instance if found.
     *
     * @param UserProvider $provider
     * @param string       $username
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function retrieveAuthenticatedUser(UserProvider $provider, $username)
    {
        $user = $provider->getLdapUserRepository()->findBy('samaccountname', $username);

        if (! $user) {
            return;
        }

        $model = null;

        // If we are using the DatabaseUserProvider, we must locate or import
        // the users model that is currently authenticated with SSO.
        if ($provider instanceof DatabaseUserProvider) {
            // Here we will import the LDAP user. If the user already exists in
            // our local database, it will be returned from the importer.
            $model = $provider->getLdapUserImporter()->run($user);

            if($model->save() && $model->wasRecentlyCreated) {
                event(new Imported($user, $model));
            }
        }

        $this->fireAuthenticatedEvent($user, $model);

        return $model ? $model : $user;
    }

    /**
     * Fires the imported event.
     *
     * @param Model    $user
     * @param Eloquent $model
     *
     * @return void
     */
    protected function fireImportedEvent(Model $user, Eloquent $model)
    {
        event(new Imported($user, $model));
    }

    /**
     * Fires the windows authentication event.
     *
     * @param Model      $user
     * @param mixed|null $model
     *
     * @return void
     */
    protected function fireAuthenticatedEvent(Model $user, $model = null)
    {
        event(new AuthenticatedWithWindows($user, $model));
    }

    /**
     * Retrieves the users SSO account name from our server.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     */
    protected function account($request)
    {
        return utf8_encode($request->server('AUTH_USER'));
    }

    /**
     * Retrieves the users username from their full account name.
     *
     * @param string $account
     *
     * @return string
     */
    protected function username($account)
    {
        // Username's may be prefixed with their domain,
        // we just need their account name.
        $username = explode('\\', $account);

        if (count($username) === 2) {
            [$domain, $username] = $username;
        } else {
            $username = $username[key($username)];
        }

        return $username;
    }
}
