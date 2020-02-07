<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Foundation\Testing\WithFaker;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\User;

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
}
