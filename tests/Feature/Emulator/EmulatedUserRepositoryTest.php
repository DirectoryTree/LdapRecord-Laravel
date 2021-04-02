<?php

namespace LdapRecord\Laravel\Tests\Feature\Emulator;

use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\Feature\TestUserModelStub;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\User;
use Ramsey\Uuid\Uuid;

class EmulatedUserRepositoryTest extends TestCase
{
    public function test_find_by()
    {
        DirectoryEmulator::setup();

        $users = collect([
            User::create(['cn' => 'John']),
            User::create(['cn' => 'Jane']),
            User::create(['cn' => 'Jen']),
        ]);

        $repo = new LdapUserRepository(User::class);
        $this->assertNull($repo->findBy('cn', 'Other'));
        $this->assertTrue($users->get(1)->is($repo->findBy('cn', 'Jane')));
    }

    public function test_find_by_guid()
    {
        DirectoryEmulator::setup();

        $user = User::create(['cn' => 'John', 'objectguid' => Uuid::uuid4()->toString()]);

        $repo = new LdapUserRepository(User::class);
        $this->assertNull($repo->findByGuid(Uuid::uuid4()->toString()));
        $this->assertTrue($user->is($repo->findByGuid($user->getConvertedGuid())));
    }

    public function test_find_by_model()
    {
        DirectoryEmulator::setup();

        $guid = Uuid::uuid4()->toString();

        $user = User::create(['cn' => 'John', 'objectguid' => $guid]);

        $model = new TestUserModelStub(['guid' => $guid]);

        $repo = new LdapUserRepository(User::class);
        $this->assertNull($repo->findByModel(new TestUserModelStub(['guid' => Uuid::uuid4()])));
        $this->assertTrue($user->is($repo->findByModel($model)));
    }

    public function test_find_by_credentials()
    {
        DirectoryEmulator::setup();

        $user = User::create(['cn' => 'John', 'mail' => 'jdoe@email.com']);

        $repo = new LdapUserRepository(User::class);
        $this->assertNull($repo->findByCredentials(['mail' => 'invalid@email.com']));
        $this->assertTrue($user->is($repo->findByCredentials(
            ['mail' => $user->mail[0]]
        )));
    }
}
