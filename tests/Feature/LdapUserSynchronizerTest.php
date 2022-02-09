<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Events\Import\Importing;
use LdapRecord\Laravel\Events\Import\Synchronized;
use LdapRecord\Laravel\Events\Import\Synchronizing;
use LdapRecord\Laravel\Import\UserSynchronizer;
use LdapRecord\Models\Model;
use Mockery as m;

class LdapUserSynchronizerTest extends DatabaseUserProviderTest
{
    use CreatesTestUsers;

    public function test_eloquent_model_can_be_set()
    {
        $synchronizer = new UserSynchronizer(TestUserModelStub::class, []);

        $this->assertSame(TestUserModelStub::class, $synchronizer->getEloquentModel());
        $this->assertInstanceOf(TestUserModelStub::class, $synchronizer->createEloquentModel());
    }

    public function test_config_can_be_set()
    {
        $synchronizer = new UserSynchronizer(TestUserModelStub::class, []);

        $config = ['foo' => 'bar'];

        $synchronizer->setConfig($config);

        $this->assertEquals($config, $synchronizer->getConfig());
    }

    public function test_new_ldap_user_has_guid_and_domain_set()
    {
        Event::fake();

        $ldapModel = $this->getMockLdapModel();

        $synchronizer = new UserSynchronizer(TestUserModelStub::class, ['sync_attributes' => []]);

        $model = $synchronizer->run($ldapModel);

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);

        $this->assertInstanceOf(TestUserModelStub::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertNotEmpty($model->password);
    }

    public function test_new_ldap_user_has_attributes_synchronized()
    {
        Event::fake();

        $ldapModel = $this->getMockLdapModel(['cn' => 'john', 'mail' => 'test@email.com']);

        $attributesToSynchronize = ['name' => 'cn'];

        $synchronizer = new UserSynchronizer(TestUserModelStub::class, $attributesToSynchronize);

        $model = $synchronizer->run($ldapModel);

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);

        $this->assertInstanceOf(TestUserModelStub::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertEquals('john', $model->name);
        $this->assertEquals('test@email.com', $model->email);
        $this->assertNotEmpty($model->password);
    }

    public function test_new_ldap_user_has_attributes_synchronized_via_handler()
    {
        Event::fake();

        $ldapModel = $this->getMockLdapModel(['cn' => 'john', 'mail' => 'test@email.com']);

        $synchronizer = new UserSynchronizer(TestUserModelStub::class, [
            'sync_attributes' => [TestLdapUserAttributeHandler::class],
        ]);

        $model = $synchronizer->run($ldapModel);

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);

        $this->assertInstanceOf(TestUserModelStub::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertEquals('jake', $model->name);
        $this->assertEquals('test@email.com', $model->email);
        $this->assertFalse($model->exists);
        $this->assertNotEmpty($model->password);
        $this->assertFalse(Hash::needsRehash($model->password));
    }

    public function test_ldap_sync_attributes_can_be_string()
    {
        Event::fake();

        $ldapModel = $this->getMockLdapModel(['cn' => 'john', 'mail' => 'test@email.com']);

        $synchronizer = new UserSynchronizer(TestUserModelStub::class, [
            'sync_attributes' => TestLdapUserAttributeHandler::class,
        ]);

        $model = $synchronizer->run($ldapModel);

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);

        $this->assertInstanceOf(TestUserModelStub::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertEquals('jake', $model->name);
        $this->assertEquals('test@email.com', $model->email);
        $this->assertFalse($model->exists);
        $this->assertNotEmpty($model->password);
        $this->assertFalse(Hash::needsRehash($model->password));
    }

    public function test_password_is_synchronized_when_enabled()
    {
        Event::fake();

        $ldapModel = $this->getMockLdapModel();

        $synchronizer = new UserSynchronizer(TestUserModelStub::class, [
            'sync_passwords' => true,
            'sync_attributes' => [],
        ]);

        $password = 'secret';

        $model = $synchronizer->run($ldapModel, ['password' => $password]);

        Event::assertDispatched(Importing::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);

        $this->assertInstanceOf(TestUserModelStub::class, $model);
        $this->assertTrue(Hash::check($password, $model->password));
    }

    public function test_password_is_not_updated_when_sync_is_disabled_and_password_is_already_set()
    {
        Event::fake();

        $ldapModel = $this->getMockLdapModel(['']);

        $synchronizer = new UserSynchronizer(TestUserModelStub::class, [
            'sync_passwords' => false,
            'sync_attributes' => [],
        ]);

        $model = $this->createTestUser([
            'guid' => $ldapModel->getConvertedGuid(),
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'initial',
        ]);

        $this->assertEquals('initial', $model->password);

        $imported = $synchronizer->run($ldapModel);

        $imported->save();

        Event::assertNotDispatched(Importing::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);

        $this->assertTrue($model->is($imported));
        $this->assertEquals('initial', $imported->password);
    }

    public function test_user_is_not_updated_when_the_same_hashed_password_already_exists()
    {
        Event::fake();

        $ldapModel = $this->getMockLdapModel();

        $synchronizer = new UserSynchronizer(TestUserModelStub::class, [
            'sync_passwords' => true,
            'sync_attributes' => [],
        ]);

        $hashedPassword = Hash::make('secret');

        $model = $this->createTestUser([
            'guid' => $ldapModel->getConvertedGuid(),
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => $hashedPassword,
        ]);

        $initialUpdateTimestamp = $model->updated_at;

        $this->assertEquals($hashedPassword, $model->password);

        $imported = $synchronizer->run($ldapModel, ['password' => 'secret']);

        $imported->save();

        Event::assertNotDispatched(Importing::class);
        Event::assertDispatched(Synchronizing::class);
        Event::assertDispatched(Synchronized::class);

        $this->assertTrue($imported->is($model));
        $this->assertTrue(Hash::check('secret', $model->password));
        $this->assertEquals($initialUpdateTimestamp, $model->fresh()->updated_at);
    }

    protected function getMockLdapModel(array $attributes = [])
    {
        $ldapModel = m::mock(Model::class);
        $ldapModel->shouldReceive('getConvertedGuid')->andReturn('guid');
        $ldapModel->shouldReceive('getConnectionName')->andReturn('default');

        foreach ($attributes as $name => $value) {
            $ldapModel->shouldReceive('getFirstAttribute')->withArgs([$name])->andReturn($value);
        }

        return $ldapModel;
    }
}

class TestLdapUserAttributeHandler
{
    public function handle($ldapModel, $testUser)
    {
        $testUser->name = 'jake';
        $testUser->email = $ldapModel->getFirstAttribute('mail');
    }
}
