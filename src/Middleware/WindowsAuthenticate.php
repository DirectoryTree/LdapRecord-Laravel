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
     * The server key to use for fetching user SSO information.
     *
     * @var string
     */
    public static $serverKey = 'AUTH_USER';

    /**
     * The username attribute to use for locating users.
     *
     * @var string
     */
    public static $username = 'samaccountname';

    /**
     * Whether domain verification is enabled.
     *
     * @var bool
     */
    public static $domainVerification = true;

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
     * Define the server key to use for fetching user SSO information.
     *
     * @param string $key
     *
     * @return void
     */
    public static function serverKey($key)
    {
        static::$serverKey = $key;
    }

    /**
     * Define the username attribute for locating users.
     *
     * @param string $attribute
     *
     * @return void
     */
    public static function username($attribute)
    {
        static::$username = $attribute;
    }

    /**
     * Bypass domain verification when logging in users.
     *
     * @return void
     */
    public static function bypassDomainVerification()
    {
        static::$domainVerification = false;
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
        [$username, $domain] = array_pad(
            array_reverse(explode('\\', $this->account($request))), 2, null
        );

        if (empty($username)) {
            return;
        }

        if (empty($guards)) {
            $guards = [null];
        }

        if ($this->authenticated($guards)) {
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
     * Determine if the user is authenticated in any of the given guards.
     *
     * @param array $guards
     *
     * @return bool
     */
    protected function authenticated(array $guards)
    {
        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                return true;
            }
        }

        return false;
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

        // Here we will determine if the current provider in use uses database
        // synchronization. We will execute the LDAP importer in such case,
        // synchronizing the user and saving their database model.
        $model = $provider instanceof DatabaseUserProvider ?
            $provider->getLdapUserImporter()->run($user) :
            null;

        // Here we will use the LDAP user authenticator to validate that the single-sign-on
        // user is allowed to sign into our application. For our callback, we will always
        // return true, since they have already authenticated against our web server.
        $allowedToAuthenticate = $provider->getLdapUserAuthenticator()
            ->setEloquentModel($model)
            ->attemptOnceUsing(function () {
                return true;
            }, $user);

        if ($allowedToAuthenticate) {
            if ($model && $model->save() && $model->wasRecentlyCreated) {
                $this->fireImportedEvent($user, $model);
            }

            $this->fireAuthenticatedEvent($user, $model);

            return $model ? $model : $user;
        }
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
        return $repository->findBy(static::$username, $username);
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
        if (! static::$domainVerification) {
            return true;
        }

        // If an empty domain is given, we won't allow the user to authenticate as we will
        // not be able to determine whether the user retrieved from the LDAP server is
        // in-fact the user who has authenticated on our server via single sign on.
        if (empty($domain)) {
            return false;
        }

        // To start, we will explode the users distinguished name into relative distinguished
        // names. This will allow us to identify and pull the domain components from it
        // which may contain the single-sign-on users authenticated domain name.
        $components = array_map(function ($rdn) {
            return strtolower($rdn);
        }, ldap_explode_dn($user->getDn(), $onlyValues = false));

        // Now we will filter the users relative distinguished names and ensure we are
        // searching through domain components only. We don't want other attributes
        // included in this check, otherwise it could result in false positives.
        $domainComponents = array_filter($components, function ($rdn) {
            return strpos($rdn, 'dc') !== false;
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
        return utf8_encode($request->server(static::$serverKey));
    }
}
