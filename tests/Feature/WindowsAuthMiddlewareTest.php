<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Exception;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use LdapRecord\Laravel\Auth\NoDatabaseUserProvider;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Laravel\Events\Auth\CompletedWithWindows;
use LdapRecord\Laravel\Events\Import\Imported;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\LdapRecord;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Middleware\UserDomainValidator;
use LdapRecord\Laravel\Middleware\WindowsAuthenticate;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Model;
use Mockery as m;

class WindowsAuthMiddlewareTest extends DatabaseTestCase
{
    use CreatesTestUsers;

    protected function tearDown(): void
    {
        // Reset all static properties.
        WindowsAuthenticate::$guards = null;
        WindowsAuthenticate::$serverKey = 'AUTH_USER';
        WindowsAuthenticate::$username = 'samaccountname';
        WindowsAuthenticate::$domainVerification = true;
        WindowsAuthenticate::$logoutUnauthenticatedUsers = false;
        WindowsAuthenticate::$rememberAuthenticatedUsers = false;
        WindowsAuthenticate::$userResolverFallback = null;
        WindowsAuthenticate::$userDomainExtractor = null;
        WindowsAuthenticate::$userDomainValidator = UserDomainValidator::class;

        LdapRecord::$failingQuietly = true;

        parent::tearDown();
    }

    public function test_request_continues_if_user_is_not_set_in_the_server_params()
    {
        Event::fake();

        app(WindowsAuthenticate::class)->handle(new Request, function () {
            $this->assertTrue(true);
        });

        Event::assertNotDispatched(Importing::class);
        Event::assertNotDispatched(Imported::class);
        Event::assertNotDispatched(Synchronizing::class);
        Event::assertNotDispatched(Synchronized::class);
        Event::assertNotDispatched(CompletedWithWindows::class);
    }

    public function test_request_continues_if_user_is_already_logged_in()
    {
        Event::fake();

        auth()->login($this->createTestUser([
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'password' => 'secret',
        ]));

        app(WindowsAuthenticate::class)->handle(new Request, function () {
            $this->assertTrue(true);
        });

        Event::assertNotDispatched(Importing::class);
        Event::assertNotDispatched(Imported::class);
        Event::assertNotDispatched(Synchronizing::class);
        Event::assertNotDispatched(Synchronized::class);
        Event::assertNotDispatched(CompletedWithWindows::class);
    }

    public function test_request_continues_if_ldap_user_cannot_be_located()
    {
        Event::fake();

        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        LdapRecord::locateUsersUsing(function () {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturnNull();

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () {
            $this->assertTrue(true);
            $this->assertFalse(auth()->check());
        });

        Event::assertNotDispatched(Importing::class);
        Event::assertNotDispatched(Imported::class);
        Event::assertNotDispatched(Synchronizing::class);
        Event::assertNotDispatched(Synchronized::class);
        Event::assertNotDispatched(CompletedWithWindows::class);
    }

    public function test_authenticates_with_database_user_provider()
    {
        WindowsAuthenticate::rememberAuthenticatedUsers();

        Event::fake();

        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        $user = new User([
            'cn' => 'SteveBauman',
            'mail' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () use ($user) {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $model = auth()->user();

            $this->assertTrue($model->exists);
            $this->assertTrue($model->wasRecentlyCreated);
            $this->assertEquals($user->getConvertedGuid(), $model->guid);
            $this->assertEquals($user->getFirstAttribute('cn'), $model->name);
            $this->assertEquals($user->getFirstAttribute('mail'), $model->email);

            $this->assertSame(auth()->user(), $model);
            $this->assertNotEmpty(auth()->user()->getRememberToken());
        });

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Imported::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);
        Event::assertDispatched(CompletedWithWindows::class);
    }

    public function test_authenticates_with_plain_user_provider()
    {
        Event::fake();

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () use ($user) {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $this->assertSame(auth()->user(), $user);
        });

        Event::assertDispatched(CompletedWithWindows::class);

        Event::assertNotDispatched(Importing::class);
        Event::assertNotDispatched(Imported::class);
        Event::assertNotDispatched(Synchronizing::class);
        Event::assertNotDispatched(Synchronized::class);
    }

    public function test_server_key_can_be_set()
    {
        WindowsAuthenticate::serverKey('FOO');

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () use ($user) {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('FOO', 'Local\SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $this->assertSame(auth()->user(), $user);
        });
    }

    public function test_username_can_be_set()
    {
        WindowsAuthenticate::username('foo');

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () use ($user) {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['foo', 'SteveBauman'])->andReturn($user);

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $this->assertSame(auth()->user(), $user);
        });
    }

    public function test_domain_verification_is_enabled_by_default()
    {
        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () use ($user) {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () {
            $this->assertNull(auth()->user());
        });
    }

    public function test_domain_verification_can_be_disabled()
    {
        WindowsAuthenticate::bypassDomainVerification();

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () use ($user) {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $this->assertSame(auth()->user(), $user);
        });
    }

    public function test_authentication_rules_are_used()
    {
        $this->setupPlainUserProvider([
            'rules' => [WindowsAuthRuleStub::class],
        ]);

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () use ($user) {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andReturn($user);

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () {
            $this->assertNull(auth()->user());
            $this->assertTrue($_SERVER[WindowsAuthRuleStub::class]);
        });
    }

    public function test_users_are_logged_out_if_enabled_and_user_is_not_authenticated_via_sso()
    {
        WindowsAuthenticate::logoutUnauthenticatedUsers();

        auth()->login($this->createTestUser([
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'password' => 'secret',
        ]));

        $this->assertTrue(auth()->check());

        app(WindowsAuthenticate::class)->handle(new Request, function () {
            $this->assertFalse(auth()->check());
        });
    }

    public function test_guards_can_be_set_manually()
    {
        WindowsAuthenticate::guards('invalid');

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'Local\SteveBauman');
        });

        $this->expectException(InvalidArgumentException::class);

        app(WindowsAuthenticate::class)->handle($request, function () {
            // Do nothing.
        });
    }

    public function test_exception_is_caught_when_resolving_users()
    {
        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () {
            $repository = m::mock(LdapUserRepository::class);

            $repository->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andThrow(new Exception('Failed'));

            return $repository;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        app(WindowsAuthenticate::class)->handle($request, function () {
            $this->assertNull(auth()->user());
        });
    }

    public function test_exception_is_thrown_when_resolving_users_if_failing_loudly()
    {
        LdapRecord::failLoudly();

        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () {
            $users = m::mock(LdapUserRepository::class);

            $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andThrow(new Exception('Failed'));

            return $users;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        $this->expectExceptionMessage('Failed');

        app(WindowsAuthenticate::class)->handle($request, function () {
            // Do nothing.
        });
    }

    public function test_fallback_is_used_when_failing_to_retrieve_user()
    {
        $this->setupPlainUserProvider();

        $user = new User([
            'cn' => 'SteveBauman',
            'userprincipalname' => 'sbauman@local.com',
            'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);

        $user->setDn('cn=SteveBauman,ou=Users,dc=local,dc=com');

        LdapRecord::locateUsersUsing(function () {
            $users = m::mock(LdapUserRepository::class);

            $users->shouldReceive('findBy')->once()->withArgs(['samaccountname', 'SteveBauman'])->andThrow(new Exception('Failed'));

            return $users;
        });

        $request = tap(new Request, function ($request) {
            $request->server->set('AUTH_USER', 'SteveBauman');
        });

        WindowsAuthenticate::fallback(function ($provider, $username, $domain) use ($user) {
            $this->assertInstanceOf(NoDatabaseUserProvider::class, $provider);
            $this->assertEquals('SteveBauman', $username);
            $this->assertNull($domain);

            return $user;
        });

        app(WindowsAuthenticate::class)->handle($request, function () use ($user) {
            $this->assertTrue(auth()->check());
            $this->assertEquals($user, auth()->user());
        });
    }
}

class WindowsAuthRuleStub implements Rule
{
    public function passes(Model $user, ?Eloquent $model = null): bool
    {
        $_SERVER[self::class] = true;

        return false;
    }
}
