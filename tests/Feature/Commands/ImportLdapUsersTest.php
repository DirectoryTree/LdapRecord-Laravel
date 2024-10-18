<?php

namespace LdapRecord\Laravel\Tests\Feature\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Auth\AuthenticatesWithLdap;
use LdapRecord\Laravel\Auth\HasLdapUser;
use LdapRecord\Laravel\Auth\LdapAuthenticatable;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use LdapRecord\Models\Collection;
use LdapRecord\Query\Model\Builder;
use Mockery as m;

class ImportLdapUsersTest extends DatabaseTestCase
{
    public function test_command_exits_when_chunk_and_delete_missing_options_are_used()
    {
        $this->artisan('ldap:import', ['--chunk' => 1000, '--delete-missing' => true])
            ->expectsOutput("The 'chunk' and 'delete-missing' options cannot be used together.")
            ->assertExitCode(Command::INVALID);
    }

    public function test_command_exits_when_provider_does_not_exist()
    {
        $this->artisan('ldap:import', ['provider' => 'invalid'])
            ->expectsOutput('Authentication provider [invalid] does not exist. Please check your config/auth.php file.')
            ->assertExitCode(Command::FAILURE);
    }

    public function test_command_exits_when_plain_provider_is_used()
    {
        $this->setupPlainUserProvider();

        $this->artisan('ldap:import', ['provider' => 'ldap-plain'])
            ->expectsOutput('Authentication provider [ldap-plain] is not configured for database synchronization. Please check your config/auth.php file.')
            ->assertExitCode(Command::INVALID);
    }

    public function test_message_is_shown_when_no_users_are_found_for_importing()
    {
        $this->setupDatabaseUserProvider();

        $repo = m::mock(LdapUserRepository::class, function ($repo) {
            $query = m::mock(Builder::class);
            $query->shouldReceive('paginate')->once()->andReturn(new Collection);

            $repo->shouldReceive('query')->once()->andReturn($query);
        });

        $provider = $this->createDatabaseUserProvider($repo);

        Auth::shouldReceive('createUserProvider')->once()->withArgs(['ldap-database'])->andReturn($provider);

        $this->artisan('ldap:import', ['provider' => 'ldap-database'])
            ->expectsOutput('There were no users found to import.')
            ->assertExitCode(Command::SUCCESS);
    }

    public function test_users_are_imported_into_the_database()
    {
        $users = new Collection([
            new LdapUser([
                'cn' => 'Steve Bauman',
                'mail' => 'sbauman@test.com',
                'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
            ]),
        ]);

        $repo = m::mock(LdapUserRepository::class, function ($repo) use ($users) {
            $query = m::mock(Builder::class);
            $query->shouldReceive('paginate')->once()->andReturn($users);

            $repo->shouldReceive('query')->once()->andReturn($query);
        });

        $synchronizer = $this->createLdapUserSynchronizer(TestImportUserModelStub::class, [
            'sync_attributes' => ['name' => 'cn', 'email' => 'mail'],
        ]);

        $provider = $this->createDatabaseUserProvider($repo, $this->createLdapUserAuthenticator(), $synchronizer);

        Auth::shouldReceive('createUserProvider')->once()->withArgs(['ldap-database'])->andReturn($provider);

        $this->artisan('ldap:import', ['provider' => 'ldap-database', '--no-interaction'])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('users', [
            'domain' => 'default',
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'guid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);
    }

    public function test_users_are_not_imported_into_the_database_when_min_users_is_not_reached()
    {
        $users = new Collection([
            new LdapUser([
                'cn' => 'Steve Bauman',
                'mail' => 'sbauman@test.com',
                'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
            ]),
        ]);

        $repo = m::mock(LdapUserRepository::class, function ($repo) use ($users) {
            $query = m::mock(Builder::class);
            $query->shouldReceive('paginate')->once()->andReturn($users);

            $repo->shouldReceive('query')->once()->andReturn($query);
        });

        $synchronizer = $this->createLdapUserSynchronizer(TestImportUserModelStub::class, [
            'sync_attributes' => ['name' => 'cn', 'email' => 'mail'],
        ]);

        $provider = $this->createDatabaseUserProvider($repo, $this->createLdapUserAuthenticator(), $synchronizer);

        Auth::shouldReceive('createUserProvider')->once()->withArgs(['ldap-database'])->andReturn($provider);

        $this->artisan('ldap:import', ['provider' => 'ldap-database', '--no-interaction', '--min-users' => 2])
            ->expectsOutput('Unable to complete import. A minimum of [2] users has been set, while only [1] were returned.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('users', [
            'domain' => 'default',
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'guid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);
    }

    public function test_users_are_imported_into_the_database_via_chunk()
    {
        $users = new Collection([
            new LdapUser([
                'cn' => 'Steve Bauman',
                'mail' => 'sbauman@test.com',
                'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
            ]),
        ]);

        $repo = m::mock(LdapUserRepository::class, function ($repo) use ($users) {
            $query = m::mock(Builder::class);

            $query->shouldReceive('chunk')->once()->with(10, m::on(function ($callback) use ($users) {
                $callback($users);

                return true;
            }));

            $repo->shouldReceive('query')->once()->andReturn($query);
        });

        $synchronizer = $this->createLdapUserSynchronizer(TestImportUserModelStub::class, [
            'sync_attributes' => ['name' => 'cn', 'email' => 'mail'],
        ]);

        $provider = $this->createDatabaseUserProvider($repo, $this->createLdapUserAuthenticator(), $synchronizer);

        Auth::shouldReceive('createUserProvider')->once()->withArgs(['ldap-database'])->andReturn($provider);

        $this->artisan('ldap:import', ['provider' => 'ldap-database', '--no-interaction', '--chunk' => 10])
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseHas('users', [
            'domain' => 'default',
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'guid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);
    }

    public function test_users_are_not_imported_into_the_database_via_chunk_when_min_users_is_not_reached()
    {
        $users = new Collection([
            new LdapUser([
                'cn' => 'Steve Bauman',
                'mail' => 'sbauman@test.com',
                'objectguid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
            ]),
        ]);

        $repo = m::mock(LdapUserRepository::class, function ($repo) use ($users) {
            $query = m::mock(Builder::class);

            $query->shouldReceive('chunk')->once()->with(10, m::on(function ($callback) use ($users) {
                $callback($users);

                return true;
            }));

            $repo->shouldReceive('query')->once()->andReturn($query);
        });

        $synchronizer = $this->createLdapUserSynchronizer(TestImportUserModelStub::class, [
            'sync_attributes' => ['name' => 'cn', 'email' => 'mail'],
        ]);

        $provider = $this->createDatabaseUserProvider($repo, $this->createLdapUserAuthenticator(), $synchronizer);

        Auth::shouldReceive('createUserProvider')->once()->withArgs(['ldap-database'])->andReturn($provider);

        $this->artisan('ldap:import', ['provider' => 'ldap-database', '--no-interaction', '--chunk' => 10, '--min-users' => 2])
            ->expectsOutput('Unable to complete import. A minimum of [2] users has been set, while only [1] were returned.')
            ->assertExitCode(Command::SUCCESS);

        $this->assertDatabaseMissing('users', [
            'domain' => 'default',
            'name' => 'Steve Bauman',
            'email' => 'sbauman@test.com',
            'guid' => 'bf9679e7-0de6-11d0-a285-00aa003049e2',
        ]);
    }
}

class TestImportUserModelStub extends User implements LdapAuthenticatable
{
    use AuthenticatesWithLdap, HasLdapUser, SoftDeletes;

    protected $guarded = [];

    protected $table = 'users';
}
