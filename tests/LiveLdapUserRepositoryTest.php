<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Testing\FakeSearchableDirectory;
use LdapRecord\Models\ActiveDirectory\User;
use Ramsey\Uuid\Uuid;

class LiveLdapUserRepositoryTest extends TestCase
{
    public function test_find_by()
    {
        FakeSearchableDirectory::setup();

        $users = collect([
            User::create(['cn' => 'John']),
            User::create(['cn' => 'Jane']),
            User::create(['cn' => 'Jen']),
        ]);

        $repo = new LdapUserRepository(User::class);
        $this->assertTrue($users->get(1)->is($repo->findBy('cn', 'Jane')));
    }

    public function test_find_by_guid()
    {
        FakeSearchableDirectory::setup();

        $user = User::create([
            'cn' => 'John',
            'objectguid' => Uuid::uuid4(),
        ]);

        $repo = new LdapUserRepository(User::class);
        $this->assertTrue($user->is($repo->findByGuid($user->getConvertedGuid())));
    }
}
