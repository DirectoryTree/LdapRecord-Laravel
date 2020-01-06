<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Events\DiscoveredWithCredentials;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Model;
use Mockery as m;

class DatabaseUserProviderTest extends TestCase
{
    use CreatesTestUsers;

    public function test_importer_can_be_retrieved()
    {
        $importer = new LdapUserImporter(TestUser::class, []);
        $provider = $this->createDatabaseUserProvider();
        $this->assertSame($importer, $provider->getLdapUserImporter());
    }

    public function test_retrieve_by_id_uses_eloquent()
    {
        $model = $this->createTestUser([
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'secret',
        ]);

        $provider = $this->createDatabaseUserProvider();

        $this->assertTrue($model->is($provider->retrieveById($model->id)));
    }

    public function test_retrieve_by_token_uses_eloquent()
    {
        $model = $this->createTestUser([
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'secret',
            'remember_token' => 'token',
        ]);

        $provider = $this->createDatabaseUserProvider();

        $this->assertTrue($model->is($provider->retrieveByToken($model->id, $model->remember_token)));
    }

    public function test_update_remember_token_uses_eloquent()
    {
        $model = $this->createTestUser([
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'secret',
            'remember_token' => 'token',
        ]);

        $provider = $this->createDatabaseUserProvider();

        $provider->updateRememberToken($model, 'new-token');
        $this->assertEquals('new-token', $model->fresh()->remember_token);
    }

    public function test_retrieve_by_credentials_returns_unsaved_database_model()
    {
        $this->expectsEvents([DiscoveredWithCredentials::class]);

        $credentials = ['samaccountname' => 'jdoe', 'password' => 'secret'];

        $ldapUser = $this->getMockLdapModel([
            'cn' => 'John Doe',
            'mail' => 'jdoe@test.com',
        ]);

        $repo = m::mock(LdapUserRepository::class);
        $repo->shouldReceive('findByCredentials')->once()->withArgs([$credentials])->andReturn($ldapUser);

        $provider = $this->createDatabaseUserProvider($repo);

        $databaseModel = $provider->retrieveByCredentials($credentials);
        $this->assertFalse($databaseModel->exists);
        $this->assertEquals('John Doe', $databaseModel->name);
        $this->assertEquals('jdoe@test.com', $databaseModel->email);
        $this->assertFalse(Hash::needsRehash($databaseModel->password));
    }

    public function test_validate_credentials_returns_false_when_no_database_model_is_set()
    {
        $databaseModel = new TestUser;

        $provider = $this->createDatabaseUserProvider();
        $this->assertFalse($provider->validateCredentials($databaseModel, ['password' => 'secret']));
        $this->assertFalse($databaseModel->exists);
    }

    public function test_validate_credentials_saves_database_model_after_passing()
    {
        $this->expectsEvents([DiscoveredWithCredentials::class]);

        $credentials = ['samaccountname' => 'jdoe', 'password' => 'secret'];

        $ldapUser = $this->getMockLdapModel([
            'cn' => 'John Doe',
            'mail' => 'jdoe@test.com',
        ]);

        $repo = m::mock(LdapUserRepository::class);
        $repo->shouldReceive('findByCredentials')->once()->withArgs([$credentials])->andReturn($ldapUser);

        $auth = m::mock(LdapUserAuthenticator::class);
        $auth->shouldReceive('setEloquentModel')->once()->withArgs([TestUser::class]);
        $auth->shouldReceive('attempt')->once()->withArgs([$ldapUser, 'secret'])->andReturnTrue();

        $provider = $this->createDatabaseUserProvider($repo, $auth);

        $databaseModel = $provider->retrieveByCredentials($credentials);
        $provider->validateCredentials($databaseModel, ['password' => 'secret']);
        $this->assertTrue($databaseModel->exists);
        $this->assertTrue($databaseModel->wasRecentlyCreated);
    }

    protected function getMockLdapModel(array $attributes = [])
    {
        $ldapModel = m::mock(Model::class);
        $ldapModel->shouldReceive('getName')->andReturn('name');
        $ldapModel->shouldReceive('getConvertedGuid')->andReturn('guid');
        $ldapModel->shouldReceive('getConnectionName')->andReturn('default');

        foreach ($attributes as $name => $value) {
            $ldapModel->shouldReceive('getFirstAttribute')->withArgs([$name])->andReturn($value);
        }

        return $ldapModel;
    }

    protected function createDatabaseUserProvider(
        LdapUserRepository $repo = null,
        LdapUserAuthenticator $auth = null,
        LdapUserImporter $importer = null,
        EloquentUserProvider $eloquent = null
    ) {
        return new DatabaseUserProvider(
            $repo ?? $this->createLdapUserRepository(),
            $auth ?? $this->createLdapUserAuthenticator(),
            $importer ?? $this->createLdapUserImporter(),
            $eloquent ?? $this->createEloquentUserProvider()
        );
    }

    protected function createLdapUserRepository($model = null)
    {
        return new LdapUserRepository($model ?? User::class);
    }

    protected function createLdapUserAuthenticator()
    {
        return new LdapUserAuthenticator();
    }

    protected function createLdapUserImporter($eloquentModel = null, array $config = [])
    {
        return new LdapUserImporter($eloquentModel ?? TestUser::class, $config);
    }

    protected function createEloquentUserProvider($model = null)
    {
        return new EloquentUserProvider(app('hash'), $model ?? TestUser::class);
    }
}
