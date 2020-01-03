<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Events\Importing;
use LdapRecord\Laravel\Events\Synchronized;
use LdapRecord\Laravel\Events\Synchronizing;
use Mockery as m;
use LdapRecord\Laravel\LdapUserImporter;
use LdapRecord\Models\Model;

class LdapUserImporterTest extends TestCase
{
    public function test_eloquent_model_can_be_set()
    {
        $importer = new LdapUserImporter(TestUser::class, []);
        $this->assertSame(TestUser::class, $importer->getEloquentModel());
        $this->assertInstanceOf(TestUser::class, $importer->getNewEloquentModel());
    }

    public function test_new_ldap_user_has_guid_and_domain_set()
    {
        $ldapModel = m::mock(Model::class);
        $ldapModel->shouldReceive('getConnection')->once()->andReturn('default');
        $ldapModel->shouldReceive('getConvertedGuid')->twice()->andReturn('guid');

        $importer = new LdapUserImporter(TestUser::class, []);

        $this->expectsEvents([
            Importing::class,
            Synchronizing::class,
            Synchronized::class,
        ]);

        $model = $importer->run($ldapModel);
        $this->assertInstanceOf(TestUser::class, $model);
        $this->assertEquals('guid', $model->getLdapGuid());
        $this->assertEquals('default', $model->getLdapDomain());
        $this->assertEquals(['domain' => 'default', 'guid' => 'guid'], $model->getAttributes());
    }
}
