<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Events\DeletedMissing;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\AccountControl;

class LiveImportTest extends DatabaseTestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupDatabaseUserProvider();

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

        $this->artisan('ldap:import', ['provider' => 'ldap-database', '--no-interaction'])
            ->assertExitCode(0);

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

    public function test_disabled_users_are_soft_deleted_when_flag_is_set()
    {
        User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
            'userAccountControl' => (new AccountControl)->accountIsDisabled(),
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
            'userAccountControl' => (new AccountControl)->accountIsNormal(),
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

        $this->setupDatabaseUserProvider();

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
            return $event->ids->count() == 1
                && $event->ids->first() == $missingLdapUser->id
                && $event->ldap instanceof User
                && $event->eloquent instanceof TestUserModelStub;
        });

        $this->assertTrue($missingLdapUser->fresh()->trashed());

        $this->assertFalse($importedLdapUser->fresh()->trashed());
        $this->assertTrue($deletedLocalUser->fresh()->trashed());
        $this->assertFalse($existingLocalUser->fresh()->trashed());
        $this->assertFalse($otherDomainLdapUser->fresh()->trashed());
    }
}
