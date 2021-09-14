<?php

namespace LdapRecord\Laravel\Middleware;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Arr;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Auth\UserProvider;
use LdapRecord\Laravel\Events\Auth\CompletedWithWindows;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Saved;
use LdapRecord\Laravel\LdapRecord;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\Model;

class WindowsAuthenticate
{
    /**
     * The guards to use for SSO authentication.
     *
     * @var null|array
     */
    public static $guards;

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
     * Whether unauthenticated SSO users are logged out.
     *
     * @var bool
     */
    public static $logoutUnauthenticatedUsers = false;

    /**
     * Whether authenticated SSO users are remembered upon login.
     *
     * @var bool
     */
    public static $rememberAuthenticatedUsers = false;

    /**
     * The user domain extractor callback.
     *
     * @var Closure|null
     */
    public static $userDomainExtractor;

    /**
     * The user domain validator class/callback.
     *
     * @var Closure|string
     */
    public static $userDomainValidator = UserDomainValidator::class;

    /**
     * The fallback callback to resolve users with.
     *
     * @var Closure|string|null
     */
    public static $userResolverFallback;

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
     * Set the guards to use for authentication.
     *
     * @param string|array $guards
     */
    public static function guards($guards)
    {
        static::$guards = Arr::wrap($guards);
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
     * Force logout unauthenticated SSO users.
     *
     * @return void
     */
    public static function logoutUnauthenticatedUsers()
    {
        static::$logoutUnauthenticatedUsers = true;
    }

    /**
     * Remember authenticated SSO users permanently.
     *
     * @return void
     */
    public static function rememberAuthenticatedUsers()
    {
        static::$rememberAuthenticatedUsers = true;
    }

    /**
     * Set the callback to extract domains from the users username.
     *
     * @param Closure $callback
     *
     * @return void
     */
    public static function extractDomainUsing(Closure $callback)
    {
        static::$userDomainExtractor = $callback;
    }

    /**
     * Register a class / callback that should be used to validate domains.
     *
     * @param Closure|string $callback
     *
     * @return void
     */
    public static function validateDomainUsing($callback)
    {
        static::$userDomainValidator = $callback;
    }

    /**
     * Set the callback to resolve users by when retrieving the authenticated user fails.
     *
     * @param Closure|string|null $callback
     *
     * @return void
     */
    public static function fallback($callback = null)
    {
        static::$userResolverFallback = $callback;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param Closure                  $next
     * @param string[]                 ...$guards
     *
     * @return mixed
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, static::$guards ?? $guards);

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
        $extractor = static::$userDomainExtractor ?: function ($account) {
            return array_pad(
                array_reverse(explode('\\', $account)),
                2,
                null
            );
        };

        $account = $extractor($this->account($request));

        [$username, $domain] = array_pad(
            Arr::wrap($account),
            2,
            null
        );

        if (empty($guards)) {
            $guards = [null];
        }

        if (empty($username)) {
            return static::$logoutUnauthenticatedUsers
                ? $this->logout($guards)
                : null;
        }

        if ($this->authenticated($guards)) {
            return;
        }

        return $this->attempt($guards, $username, $domain);
    }

    /**
     * Attempt retrieving and logging in the authenticated user.
     *
     * @param array       $guards
     * @param string      $username
     * @param string|null $domain
     *
     * @return void
     */
    protected function attempt($guards, $username, $domain = null)
    {
        foreach ($guards as $guard) {
            $provider = $this->auth->guard($guard)->getProvider();

            if (! $provider instanceof UserProvider) {
                continue;
            }

            if (! $user = $this->retrieveAuthenticatedUser($provider, $username, $domain)) {
                continue;
            }

            $this->auth->shouldUse($guard);

            return $this->auth->login($user, static::$rememberAuthenticatedUsers);
        }
    }

    /**
     * Logout of all the given authenticated guards.
     *
     * @param $guards
     */
    protected function logout($guards)
    {
        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->guard($guard)->logout();
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
     * @param string       $username
     * @param string|null  $domain
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function retrieveAuthenticatedUser(UserProvider $provider, $username, $domain = null)
    {
        // First, we will attempt to retrieve the user from the LDAP server
        // by their username. If we don't get any result, we can bail
        // safely here and continue with the request lifecycle.
        $user = $this->getUserFromRepository($provider->getLdapUserRepository(), $username);

        if (! $user) {
            return $this->failedRetrievingUser($provider, $username, $domain);
        }

        // Next, we will attempt to validate that the user we have retrieved from LDAP server query
        // is in-fact the one who has been authenticated via SSO on our web server. This will
        // prevent users with the same username from potentially signing in as eachother.
        if (! $this->userIsApartOfDomain($user, $username, $domain)) {
            return;
        }

        // Then, we will determine if the current provider in-use uses database
        // sync. If it does, we will execute the LDAP importer, which will
        // locate and synchronize the users database model attributes.
        $model = $provider instanceof DatabaseUserProvider
            ? $provider->getLdapUserSynchronizer()->run($user)
            : null;

        // Next, we will use the LDAP user authenticator to validate that the single-sign-on
        // user is allowed to sign into our application. For our callback, we will always
        // return true, since they have already authenticated against our web server.
        $allowedToAuthenticate = $provider->getLdapUserAuthenticator()
            ->setEloquentModel($model)
            ->attemptOnceUsing(function () {
                return true;
            }, $user);

        // If the user doesn't pass the set-up LDAP authorization rules, we can
        // bail here. Even though the user has successfully authenticated
        // against the web server, they have been denied logging in.
        if (! $allowedToAuthenticate) {
            return;
        }

        // Finally, we will finish saving the users database model (if applicable)
        // and fire any required events. Once completed, we will return the
        // users model to finish authenticating them into our application.
        if ($model) {
            $this->finishModelSave($user, $model);
        }

        $this->fireAuthenticatedEvent($user, $model);

        return $model ?: $user;
    }

    /**
     * Finish saving the user's database model.
     *
     * @param Model    $user
     * @param Eloquent $model
     *
     * @return void
     */
    protected function finishModelSave(Model $user, Eloquent $model)
    {
        $model->save();

        $this->fireSavedEvent($user, $model);

        $model->wasRecentlyCreated
                ? $this->fireImportedEvent($user, $model)
                : null;
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
        try {
            return $repository->findBy(static::$username, $username);
        } catch (Exception $e) {
            if (! LdapRecord::failingQuietly()) {
                throw $e;
            }

            report($e);
        }
    }

    /**
     * Determine if the located user is apart of the domain.
     *
     * @param Model       $user
     * @param string      $username
     * @param string|null $domain
     *
     * @return bool
     */
    protected function userIsApartOfDomain(Model $user, $username, $domain = null)
    {
        if (! static::$domainVerification) {
            return true;
        }

        return with(static::$userDomainValidator, function ($callback) {
            return is_string($callback) ? new $callback : $callback;
        })($user, $username, $domain);
    }

    /**
     * Handle failure of retrieving the authenticated user.
     *
     * @param UserProvider $provider
     * @param string       $username
     * @param string|null  $domain
     *
     * @return \Illuminate\Contracts\Auth\Authenticatable|null
     */
    protected function failedRetrievingUser(UserProvider $provider, $username, $domain = null)
    {
        if (! static::$userResolverFallback) {
            return;
        }

        return with(static::$userResolverFallback, function ($callback) {
            return is_string($callback) ? new $callback : $callback;
        })($provider, $username, $domain);
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
     * Fires the saved event.
     *
     * @param Model    $user
     * @param Eloquent $model
     *
     * @return void
     */
    protected function fireSavedEvent(Model $user, Eloquent $model)
    {
        event(new Saved($user, $model));
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
        event(new CompletedWithWindows($user, $model));
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
