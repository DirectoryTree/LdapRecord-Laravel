<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Support\Facades\Hash;
use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Models\Model;
use Mockery as m;

class LdapUserImporterTest extends TestCase
{
    use CreatesTestUsers;

    public function test_eloquent_model_can_be_set()
    {
        $importer = new LdapUserImporter(TestUser::class, []);
        $this->assertSame(TestUser::class, $importer->getEloquentModel());
        $this->assertInstanceOf(TestUser::class, $importer->createEloquentModel());
    }

    public function test_new_ldap_user_has_guid_and_domain_set()
    {
        $this->expectsEvents([Importing::class, Synchronizing::class, Synchronized::class]);

        $ldapModel = $this->getMockLdapModel();

        $importer = new LdapUserImporter(TestUser::class, ['sync_attributes' => []]);

        $model = $importer->run($ldapModel);
        $this->assertInstanceOf(TestUser::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertNotEmpty($model->password);
    }

    public function test_new_ldap_user_has_attributes_synchronized()
    {
        $this->expectsEvents([Importing::class, Synchronizing::class, Synchronized::class]);

        $ldapModel = $this->getMockLdapModel(['cn' => 'john', 'mail' => 'test@email.com']);

        $attributesToSynchronize = ['name' => 'cn'];

        $importer = new LdapUserImporter(TestUser::class, $attributesToSynchronize);

        $model = $importer->run($ldapModel);
        $this->assertInstanceOf(TestUser::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertEquals('john', $model->name);
        $this->assertEquals('test@email.com', $model->email);
        $this->assertNotEmpty($model->password);
    }

    public function test_new_ldap_user_has_attributes_synchronized_via_handler()
    {
        $this->expectsEvents([Importing::class, Synchronizing::class, Synchronized::class]);

        $ldapModel = $this->getMockLdapModel(['cn' => 'john', 'mail' => 'test@email.com']);

        $importer = new LdapUserImporter(TestUser::class, [TestLdapUserAttributeHandler::class]);

        $model = $importer->run($ldapModel);
        $this->assertInstanceOf(TestUser::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertEquals('john', $model->name);
        $this->assertEquals('test@email.com', $model->email);
        $this->assertFalse($model->exists);
        $this->assertNotEmpty($model->password);
        $this->assertFalse(Hash::needsRehash($model->password));
    }

    public function test_password_is_synchronized_when_enabled()
    {
        $this->expectsEvents([Importing::class, Synchronizing::class, Synchronized::class]);

        $ldapModel = $this->getMockLdapModel();

        $importer = new LdapUserImporter(TestUser::class, ['sync_passwords' => true, 'sync_attributes' => []]);

        $password = 'secret';
        $model = $importer->run($ldapModel, $password);
        $this->assertInstanceOf(TestUser::class, $model);
        $this->assertTrue(Hash::check($password, $model->password));
    }

    public function test_password_is_not_updated_when_sync_is_disabled_and_password_is_already_set()
    {
        $this->expectsEvents([Synchronizing::class, Synchronized::class])->doesntExpectEvents([Importing::class]);

        $ldapModel = $this->getMockLdapModel(['']);

        $importer = new LdapUserImporter(TestUser::class, ['sync_passwords' => false, 'sync_attributes' => []]);

        $model = $this->createTestUser([
            'guid' => $ldapModel->getConvertedGuid(),
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => 'initial',
        ]);

        $this->assertEquals('initial', $model->password);

        $imported = $importer->run($ldapModel);
        $imported->save();

        $this->assertTrue($model->is($imported));
        $this->assertEquals('initial', $imported->password);
    }

    public function test_user_is_not_updated_when_the_same_hashed_password_already_exists()
    {
        $this->expectsEvents([Synchronizing::class, Synchronized::class])->doesntExpectEvents([Importing::class]);

        $ldapModel = $this->getMockLdapModel();

        $importer = new LdapUserImporter(TestUser::class, ['sync_passwords' => true, 'sync_attributes' => []]);

        $hashedPassword = Hash::make('secret');

        $model = $this->createTestUser([
            'guid' => $ldapModel->getConvertedGuid(),
            'name' => 'john',
            'email' => 'test@email.com',
            'password' => $hashedPassword,
        ]);

        $initialUpdateTimestamp = $model->updated_at;

        $this->assertEquals($hashedPassword, $model->password);

        $imported = $importer->run($ldapModel, 'secret');
        $imported->save();

        $this->assertTrue($imported->is($model));
        $this->assertTrue(Hash::check('secret', $model->password));
        $this->assertEquals($initialUpdateTimestamp, $model->updated_at);
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
        $testUser->name = $ldapModel->getFirstAttribute('cn');
    }
}
