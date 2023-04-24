<?php

namespace LdapRecord\Laravel\Middleware;

use Closure;
use Exception;
use Illuminate\Contracts\Auth\Authenticatable;
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
     */
    public static ?array $guards = null;

    /**
     * The server key to use for fetching user SSO information.
     */
    public static string $serverKey = 'AUTH_USER';

    /**
     * The username attribute to use for locating users.
     */
    public static string $username = 'samaccountname';

    /**
     * Whether domain verification is enabled.
     */
    public static bool $domainVerification = true;

    /**
     * Whether unauthenticated SSO users are logged out.
     */
    public static bool $logoutUnauthenticatedUsers = false;

    /**
     * Whether authenticated SSO users are remembered upon login.
     */
    public static bool $rememberAuthenticatedUsers = false;

    /**
     * The user domain extractor callback.
     */
    public static ?Closure $userDomainExtractor = null;

    /**
     * The user domain validator class/callback.
     */
    public static Closure|string $userDomainValidator = UserDomainValidator::class;

    /**
     * The fallback callback to resolve users with.
     */
    public static Closure|string|null $userResolverFallback = null;

    /**
     * The auth factory instance.
     */
    protected Auth $auth;

    /**
     * Constructor.
     */
    public function __construct(Auth $auth)
    {
        $this->auth = $auth;
    }

    /**
     * Set the guards to use for authentication.
     *
     * @param  string|array  $guards
     */
    public static function guards($guards): void
    {
        static::$guards = Arr::wrap($guards);
    }

    /**
     * Define the server key to use for fetching user SSO information.
     *
     * @param  string  $key
     */
    public static function serverKey($key): void
    {
        static::$serverKey = $key;
    }

    /**
     * Define the username attribute for locating users.
     *
     * @param  string  $attribute
     */
    public static function username($attribute): void
    {
        static::$username = $attribute;
    }

    /**
     * Bypass domain verification when logging in users.
     */
    public static function bypassDomainVerification(): void
    {
        static::$domainVerification = false;
    }

    /**
     * Force logout unauthenticated SSO users.
     */
    public static function logoutUnauthenticatedUsers(): void
    {
        static::$logoutUnauthenticatedUsers = true;
    }

    /**
     * Remember authenticated SSO users permanently.
     */
    public static function rememberAuthenticatedUsers(): void
    {
        static::$rememberAuthenticatedUsers = true;
    }

    /**
     * Set the callback to extract domains from the users username.
     */
    public static function extractDomainUsing(Closure $callback): void
    {
        static::$userDomainExtractor = $callback;
    }

    /**
     * Register a class / callback that should be used to validate domains.
     *
     * @param  Closure|string  $callback
     */
    public static function validateDomainUsing($callback): void
    {
        static::$userDomainValidator = $callback;
    }

    /**
     * Set the callback to resolve users by when retrieving the authenticated user fails.
     *
     * @param  Closure|string|null  $callback
     */
    public static function fallback($callback = null): void
    {
        static::$userResolverFallback = $callback;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string[]  ...$guards
     */
    public function handle($request, Closure $next, ...$guards)
    {
        $this->authenticate($request, static::$guards ?? $guards);

        return $next($request);
    }

    /**
     * Attempt to authenticate the LDAP user in the given guards.
     *
     * @param  \Illuminate\Http\Request  $request
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
     * @param  array  $guards
     * @param  string  $username
     * @param  string|null  $domain
     */
    protected function attempt($guards, $username, $domain = null): void
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

            $this->auth->login($user, static::$rememberAuthenticatedUsers);

            return;
        }
    }

    /**
     * Logout of all the given authenticated guards.
     */
    protected function logout($guards): void
    {
        foreach ($guards as $guard) {
            if ($this->auth->guard($guard)->check()) {
                $this->auth->guard($guard)->logout();
            }
        }
    }

    /**
     * Determine if the user is authenticated in any of the given guards.
     */
    protected function authenticated(array $guards): bool
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
     * @param  string  $username
     * @param  string|null  $domain
     */
    protected function retrieveAuthenticatedUser(UserProvider $provider, $username, $domain = null): ?Authenticatable
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
            return null;
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
            return null;
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
     */
    protected function finishModelSave(Model $user, Eloquent $model): void
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
     * @param  string  $username
     */
    protected function getUserFromRepository(LdapUserRepository $repository, $username): ?Model
    {
        try {
            return $repository->findBy(static::$username, $username);
        } catch (Exception $e) {
            if (! LdapRecord::failingQuietly()) {
                throw $e;
            }

            report($e);

            return null;
        }
    }

    /**
     * Determine if the located user is apart of the domain.
     *
     * @param  string  $username
     * @param  string|null  $domain
     */
    protected function userIsApartOfDomain(Model $user, $username, $domain = null): bool
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
     * @param  string  $username
     * @param  string|null  $domain
     */
    protected function failedRetrievingUser(UserProvider $provider, $username, $domain = null): ?Authenticatable
    {
        if (! static::$userResolverFallback) {
            return null;
        }

        return with(static::$userResolverFallback, function ($callback) {
            return is_string($callback) ? new $callback : $callback;
        })($provider, $username, $domain);
    }

    /**
     * Fires the imported event.
     */
    protected function fireImportedEvent(Model $user, Eloquent $model): void
    {
        event(new Imported($user, $model));
    }

    /**
     * Fires the saved event.
     */
    protected function fireSavedEvent(Model $user, Eloquent $model): void
    {
        event(new Saved($user, $model));
    }

    /**
     * Fires the windows authentication event.
     *
     * @param  mixed|null  $model
     */
    protected function fireAuthenticatedEvent(Model $user, $model = null): void
    {
        event(new CompletedWithWindows($user, $model));
    }

    /**
     * Retrieves the users SSO account name from our server.
     *
     * @param  \Illuminate\Http\Request  $request
     */
    protected function account($request): string
    {
        return utf8_encode($request->server(static::$serverKey));
    }
}
