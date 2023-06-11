<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Events\Import\DeletedMissing;
use LdapRecord\Laravel\Events\Import\ImportFailed;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\DatabaseTestCase;
use LdapRecord\Laravel\Tests\Feature\TestUserModelStub;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\Model;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class EmulatedImportTest extends DatabaseTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        DirectoryEmulator::setup();
    }

    public function test_ldap_users_are_imported()
    {
        $users = collect();

        for ($i = 0; $i < 10; $i++) {
            $users->add(User::create([
                'cn' => $this->faker->name,
                'mail' => $this->faker->email,
                'objectguid' => $this->faker->uuid,
            ]));
        }

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--attributes' => 'cn,mail,objectguid',
            '--no-interaction',
        ])->assertExitCode(0);

        $imported = TestUserModelStub::get();

        $this->assertCount(10, $imported);

        for ($i = 0; $i < 10; $i++) {
            $importedUser = $imported->get($i);
            $ldapUser = $users->get($i);

            $this->assertEquals($ldapUser->cn[0], $importedUser->name);
            $this->assertEquals($ldapUser->mail[0], $importedUser->email);
            $this->assertEquals('default', $importedUser->domain);
            $this->assertEquals($ldapUser->getConvertedGuid(), $importedUser->guid);
        }
    }

    public function test_import_fails_when_required_attributes_are_missing()
    {
        User::create(['cn' => $this->faker->name]);

        Event::fake();

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
        ])->assertExitCode(0);

        Event::assertDispatched(ImportFailed::class);
    }

    public function test_disabled_users_are_soft_deleted_when_flag_is_set()
    {
        User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
            'userAccountControl' => (new AccountControl)->setAccountIsDisabled(),
        ]);

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
            '--delete' => true,
        ])->assertExitCode(0);

        $created = TestUserModelStub::withTrashed()->first();

        $this->assertTrue($created->trashed());
    }

    public function test_enabled_users_who_are_soft_deleted_are_restored_when_flag_is_set()
    {
        $user = User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
            'userAccountControl' => (new AccountControl)->setAccountIsNormal(),
        ]);

        $database = TestUserModelStub::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'guid' => $user->objectguid[0],
            'password' => 'secret',
            'deleted_at' => now(),
        ]);

        $this->assertTrue($database->trashed());

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
            '--delete' => true,
            '--restore' => true,
        ])->assertExitCode(0);

        $this->assertFalse($database->fresh()->trashed());
    }

    public function test_missing_users_are_soft_deleted_when_flag_is_enabled()
    {
        Event::fake(DeletedMissing::class);

        DirectoryEmulator::setup();

        $user = User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
        ]);

        $importedLdapUser = TestUserModelStub::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'guid' => $user->objectguid[0],
            'password' => 'secret',
        ]);

        $missingLdapUser = TestUserModelStub::create([
            'name' => 'Bob Doe',
            'email' => 'bobdoe@local.com',
            'guid' => $this->faker->uuid,
            'domain' => 'default',
            'password' => 'secret',
        ]);

        $deletedLocalUser = TestUserModelStub::create([
            'name' => 'John Doe',
            'email' => 'johndoe@local.com',
            'password' => 'secret',
            'deleted_at' => now(),
        ]);

        $existingLocalUser = TestUserModelStub::create([
            'name' => 'Jane Doe',
            'email' => 'janedoe@local.com',
            'password' => 'secret',
        ]);

        $otherDomainLdapUser = TestUserModelStub::create([
            'name' => 'Betty Doe',
            'email' => 'bettydoe@local.com',
            'guid' => $this->faker->uuid,
            'domain' => 'other',
            'password' => 'secret',
        ]);

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
            '--delete-missing',
        ])->assertExitCode(0);

        $this->assertFalse($missingLdapUser->fresh()->trashed());

        $this->assertFalse($importedLdapUser->fresh()->trashed());
        $this->assertTrue($deletedLocalUser->fresh()->trashed());
        $this->assertFalse($existingLocalUser->fresh()->trashed());
        $this->assertFalse($otherDomainLdapUser->fresh()->trashed());

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
            '--delete-missing' => true,
        ])->assertExitCode(0);

        Event::assertDispatched(DeletedMissing::class, function (DeletedMissing $event) use ($missingLdapUser) {
            return $event->deleted->count() == 1
                && $event->deleted->first() == $missingLdapUser->guid
                && $event->ldapModel instanceof User
                && $event->eloquentModel instanceof TestUserModelStub;
        });

        $this->assertTrue($missingLdapUser->fresh()->trashed());

        $this->assertFalse($importedLdapUser->fresh()->trashed());
        $this->assertTrue($deletedLocalUser->fresh()->trashed());
        $this->assertFalse($existingLocalUser->fresh()->trashed());
        $this->assertFalse($otherDomainLdapUser->fresh()->trashed());
    }

    public function test_existing_users_are_synchronized_when_enabled()
    {
        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
                'sync_existing' => ['email' => 'mail'],
            ],
        ]);

        DirectoryEmulator::setup();

        $user = User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
        ]);

        $database = TestUserModelStub::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'password' => 'secret',
        ]);

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
        ])->assertExitCode(0);

        $database->refresh();

        $this->assertEquals($user->getConvertedGuid(), $database->guid);
    }

    public function test_can_use_array_syntax_for_sync_existing_configuration()
    {
        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
                'sync_existing' => [
                    'email' => [
                        'attribute' => 'mail',
                        'operator' => 'like',
                    ],
                ],
            ],
        ]);

        DirectoryEmulator::setup();

        $user = User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
        ]);

        $database = TestUserModelStub::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'password' => 'secret',
        ]);

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
        ])->assertExitCode(0);

        $database->refresh();

        $this->assertEquals($user->getConvertedGuid(), $database->guid);
    }

    public function test_existing_users_can_be_synchronized_using_raw_attributes()
    {
        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
                'sync_existing' => ['domain' => 'my-domain'],
            ],
        ]);

        DirectoryEmulator::setup();

        $user = User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
        ]);

        $database = TestUserModelStub::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'password' => 'secret',
            'domain' => 'my-domain',
        ]);

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
        ])->assertExitCode(0);

        $database->refresh();

        $this->assertEquals($user->getConvertedGuid(), $database->guid);
    }

    public function test_scopes_can_be_applied_to_command()
    {
        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => TestUserModelStub::class,
            ],
        ]);

        DirectoryEmulator::setup();

        User::create([
            'cn' => 'Foo',
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
        ]);

        User::create([
            'cn' => 'Bar',
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
        ]);

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--scopes' => TestImportUserModelScope::class,
            '--no-interaction',
        ])->assertExitCode(0);

        $this->assertCount(1, $users = TestUserModelStub::get());
        $this->assertEquals('Bar', $users->first()->name);
    }
}

class TestImportUserModelScope implements Scope
{
    public function apply(Builder $query, Model $model): void
    {
        $query->where('cn', 'Bar');
    }
}
