<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Auth\DatabaseUserProvider;
use LdapRecord\Laravel\Import\UserSynchronizer;
use LdapRecord\Laravel\LdapRecord;
use LdapRecord\Laravel\LdapUserAuthenticator;
use LdapRecord\Laravel\LdapUserRepository;
use Mockery as m;

class DatabaseUserProviderTest extends DatabaseTestCase
{
    use CreatesTestUsers;

    protected function tearDown(): void
    {
        // Reset static properties.
        LdapRecord::$failingQuietly = true;

        parent::tearDown();
    }

    public function test_importer_can_be_retrieved()
    {
        $synchronizer = new UserSynchronizer(TestUserModelStub::class, []);

        $provider = $this->createDatabaseUserProvider(
            $this->createLdapUserRepository(),
            $this->createLdapUserAuthenticator(),
            $synchronizer
        );

        $this->assertSame($synchronizer, $provider->getLdapUserSynchronizer());
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
        $databaseModel = new TestUserModelStub;

        $provider = $this->createDatabaseUserProvider();

        $this->assertFalse($provider->validateCredentials($databaseModel, ['password' => 'secret']));
        $this->assertFalse($databaseModel->exists);
    }

    public function test_validate_credentials_saves_database_model_after_passing()
    {
        $credentials = ['samaccountname' => 'jdoe', 'password' => 'secret'];

        $ldapUser = $this->getMockLdapModel([
            'cn' => 'John Doe',
            'mail' => 'jdoe@test.com',
        ]);

        $repo = m::mock(LdapUserRepository::class);
        $repo->shouldReceive('findByCredentials')->once()->withArgs([$credentials])->andReturn($ldapUser);

        $auth = m::mock(LdapUserAuthenticator::class);
        $auth->shouldReceive('setEloquentModel')->once()->withArgs([TestUserModelStub::class]);
        $auth->shouldReceive('attempt')->once()->withArgs([$ldapUser, 'secret'])->andReturnTrue();

        $provider = $this->createDatabaseUserProvider($repo, $auth);

        $databaseModel = $provider->retrieveByCredentials($credentials);
        $provider->validateCredentials($databaseModel, ['password' => 'secret']);
        $this->assertTrue($databaseModel->exists);
        $this->assertTrue($databaseModel->wasRecentlyCreated);
    }

    public function test_method_calls_are_passed_to_eloquent_user_provider()
    {
        $provider = $this->createDatabaseUserProvider();

        $model = $provider->getModel();

        $this->assertInstanceOf(Model::class, new $model);
    }

    public function test_failing_loudly_throws_exception_when_resolving_users()
    {
        LdapRecord::failLoudly();

        $provider = m::mock(DatabaseUserProvider::class)->makePartial();

        $provider->resolveUsersUsing(function () {
            throw new Exception('Failed');
        });

        $this->expectExceptionMessage('Failed');

        $provider->retrieveByCredentials([]);
    }

    public function test_rehash_password_if_required_does_nothing_when_password_column_disabled()
    {
        $synchronizer = new UserSynchronizer(TestUserModelStub::class, [
            'password_column' => false,
        ]);

        $provider = $this->createDatabaseUserProvider(synchronizer: $synchronizer);

        $provider->rehashPasswordIfRequired($model = new TestUserModelStub, ['password' => 'secret']);

        $this->assertNull($model->password);
    }
}
