<?php

namespace LdapRecord\Laravel\Middleware;

use Closure;
use LdapRecord\Models\Model;
use LdapRecord\Laravel\Domain;
use Illuminate\Contracts\Auth\Guard;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;

class WindowsAuthenticate
{
    /**
     * The authenticator implementation.
     *
     * @var Guard
     */
    protected $auth;

    /**
     * Constructor.
     *
     * @param Guard $auth
     */
    public function __construct(Guard $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure                  $next
     *
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (! $this->auth->check()) {
            $provider = $this->auth->getProvider();

            if ($provider instanceof UserProvider) {
                // Retrieve the users account name from the request.
                if ($account = $this->account($request)) {
                    // Retrieve the users username from their account name.
                    $username = $this->username($account);

                    // Finally, retrieve the users authenticatable model and log them in.
                    if ($user = $this->retrieveAuthenticatedUser($provider->getDomain(), $username)) {
                        $this->auth->login($user, $remember = true);
                    }
                }
            }
        }

        return $next($request);
    }

    /**
     * Returns the authenticatable user instance if found.
     *
     * @param Domain $domain
     * @param string $username
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function retrieveAuthenticatedUser(Domain $domain, $username)
    {
        $user = $domain->locate()->by('samaccountname', $username);

        if (! $user) {
            return;
        }

        $model = null;

        // If we are using the DatabaseUserProvider, we must locate or import
        // the users model that is currently authenticated with SSO.
        if ($domain->isUsingDatabase()) {
            // Here we will import the LDAP user. If the user already exists in
            // our local database, it will be returned from the importer.
            $model = $domain->importer()->run($user);
        }

        // Here we will validate that the authenticating user
        // passes our LDAP authentication rules in place.
        if ($domain->userValidator($user, $model)->passes()) {
            if ($model) {
                // We will sync / set the users password after
                // our model has been synchronized.
                $domain->passwordSynchronizer()->run($model);

                // We also want to save the model in case it doesn't
                // exist yet, or there are changes to be synced.
                $model->save();
            }

            $this->fireAuthenticatedEvent($user, $model);

            return $model ? $model : $user;
        }
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
