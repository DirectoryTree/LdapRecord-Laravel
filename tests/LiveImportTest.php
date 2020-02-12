<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\AccountControl;

class LiveImportTest extends DatabaseTestCase
{
    use WithFaker;

    public function test()
    {
        $this->setupDatabaseUserProvider();

        DirectoryEmulator::setup();

        $users = collect();

        foreach (range(1, 10) as $iteration) {
            $users->add(User::create([
                'cn' => $this->faker->name,
                'mail' => $this->faker->email,
                'objectguid' => $this->faker->uuid,
            ]));
        }

        $this->artisan('ldap:import', ['provider' => 'ldap-database', '--no-interaction'])
            ->assertExitCode(0);

        $created = TestUser::get();
        $this->assertCount(10, $created);
        $this->assertEquals($users->first()->mail[0], $created->first()->email);
    }

    public function test_disabled_users_are_soft_deleted_when_flag_is_set()
    {
        $this->setupDatabaseUserProvider();

        DirectoryEmulator::setup();

        User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
            'userAccountControl' => (new AccountControl)->accountIsDisabled()
        ]);

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
            '--delete' => true
        ])->assertExitCode(0);

        $created = TestUser::withTrashed()->first();
        $this->assertTrue($created->trashed());
    }

    public function test_enabled_users_who_are_soft_deleted_are_restored_when_flag_is_set()
    {
        $this->setupDatabaseUserProvider();

        DirectoryEmulator::setup();

        $user = User::create([
            'cn' => $this->faker->name,
            'mail' => $this->faker->email,
            'objectguid' => $this->faker->uuid,
            'userAccountControl' => (new AccountControl)->accountIsNormal()
        ]);

        $created = TestUser::create([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
            'guid' => $user->objectguid[0],
            'password' => 'secret',
            'deleted_at' => now(),
        ]);

        $this->assertTrue($created->trashed());

        $this->artisan('ldap:import', [
            'provider' => 'ldap-database',
            '--no-interaction',
            '--delete' => true,
            '--restore' => true,
        ])->assertExitCode(0);

        $this->assertFalse($created->fresh()->trashed());
    }
}
