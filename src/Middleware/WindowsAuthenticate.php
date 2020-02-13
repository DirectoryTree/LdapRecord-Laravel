<?php

namespace LdapRecord\Laravel\Middleware;

use Closure;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Model as Eloquent;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\Events\AuthenticatedWithWindows;
use LdapRecord\Laravel\Events\Imported;
use LdapRecord\Laravel\LdapUserRepository;
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

        list($domain, $username) = array_pad(
            explode('\\', $this->account($request)), 2, null
        );

        if (empty($domain) || empty($username)) {
            return;
        }

        foreach ($guards as $guard) {
            $provider = $this->auth->guard($guard)->getProvider();

            if (! $provider instanceof UserProvider) {
                continue;
            }

            if ($user = $this->retrieveAuthenticatedUser($provider, $domain, $username)) {
                $this->auth->shouldUse($guard);

                return $this->auth->login($user, $remember = true);
            }
        }
    }

    /**
     * Returns the authenticatable user instance if found.
     *
     * @param UserProvider $provider
     * @param string       $domain
     * @param string       $username
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function retrieveAuthenticatedUser(UserProvider $provider, $domain, $username)
    {
        $user = $this->getUserFromRepository($provider->getLdapUserRepository(), $username);

        if (! $user) {
            return;
        }

        if (! $this->userIsApartOfDomain($user, $domain)) {
            return;
        }

        $model = null;

        // If we are using the DatabaseUserProvider, we must locate or import
        // the users model that is currently authenticated with SSO.
        if ($provider instanceof DatabaseUserProvider) {
            // Here we will import the LDAP user. If the user already exists in
            // our local database, it will be returned from the importer.
            $model = $provider->getLdapUserImporter()->run($user);

            if ($model->save() && $model->wasRecentlyCreated) {
                $this->fireImportedEvent($user, $model);
            }
        }

        $this->fireAuthenticatedEvent($user, $model);

        return $model ? $model : $user;
    }

    /**
     * Get the user from the LDAP user repository by their username.
     *
     * @param LdapUserRepository $repository
     * @param string             $username
     *
     * @return Model|null
     */
    protected function getUserFromRepository(LdapUserRepository $repository, $username)
    {
        return $repository->findBy('samaccountname', $username);
    }

    /**
     * Determine if the located user is apart of the domain.
     *
     * @param Model  $user
     * @param string $domain
     *
     * @return bool
     */
    protected function userIsApartOfDomain(Model $user, $domain)
    {
        // Firstly, we will explode the users distinguished name into relative distinguished
        // names. This will allow us to identify and pull the domain components from it
        // which may contain the single-sign-on users authenticated domain name.
        $components = array_map(function ($rdn) {
            return strtolower($rdn);
        }, ldap_explode_dn($user->getDn(), $onlyValues = false));

        // Now we will filter the users relative distinguished names and ensure we are
        // searching through domain components only. We don't want other attributes
        // included in this check, otherwise it could result in false positives.
        $domainComponents = array_filter($components, function ($rdn) {
            return strpos(strtolower($rdn), 'dc') !== false;
        });

        // Here we will determine if the single sign on users domain is contained inside of
        // one of the domain components. This verifies that the user we have located from
        // the LDAP directory is in-fact the user who is signed in through single sign
        // on. If we do not check this, a user from another domain may have the same
        // sAMAccount name as another user on another domain, which we will avoid.
        foreach ($domainComponents as $component) {
            if (strpos($component, strtolower($domain)) !== false) {
                return true;
            }
        }

        return false;
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
}
