<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use LdapRecord\Laravel\Import\Synchronizer;
use LdapRecord\Laravel\ImportableFromLdap;
use LdapRecord\Laravel\LdapImportable;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\Group as LdapGroup;

class LdapSynchronizerTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_synchronizer_group_model_stubs', function (Blueprint $table) {
            $table->increments('id');
            $table->softDeletes();
            $table->string('guid')->unique()->nullable();
            $table->string('domain')->nullable();
            $table->string('name')->nullable();
        });

        DirectoryEmulator::setup();
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_synchronizer_group_model_stubs');

        parent::tearDown();
    }

    public function test_importer_fails_on_object_that_does_not_contain_guid()
    {
        $object = LdapGroup::create(['cn' => 'Group']);

        $synchronizer = new Synchronizer(TestSynchronizerGroupModelStub::class, [
            'sync_attributes' => ['name' => 'cn'],
        ]);

        $this->expectException(LdapRecordException::class);

        $synchronizer->run($object);
    }

    public function test_importer_sets_configured_attributes()
    {
        $object = LdapGroup::create([
            'objectguid' => $this->faker->uuid,
            'cn' => 'Group',
        ]);

        $synchronizer = new Synchronizer(TestSynchronizerGroupModelStub::class, [
            'sync_attributes' => ['name' => 'cn'],
        ]);

        /** @var TestSynchronizerGroupModelStub $group */
        $group = $synchronizer->run($object);

        $this->assertEquals('default', $group->getLdapDomain());
        $this->assertEquals($object->getConvertedGuid(), $group->getLdapGuid());
        $this->assertEquals($object->getName(), $group->name);
        $this->assertFalse($group->exists);
    }

    public function test_importer_locates_existing_model()
    {
        $guid = $this->faker->uuid;

        TestSynchronizerGroupModelStub::create(['guid' => $guid]);

        $object = LdapGroup::create([
            'objectguid' => $guid,
            'cn' => 'Group',
        ]);

        $synchronizer = new Synchronizer(TestSynchronizerGroupModelStub::class, [
            'sync_attributes' => ['name' => 'cn'],
        ]);

        $group = $synchronizer->run($object);

        $this->assertTrue($group->exists);
        $this->assertEquals($guid, $group->guid);
    }

    public function test_importer_locates_soft_deleted_model()
    {
        $guid = $this->faker->uuid;

        $group = TestSynchronizerGroupModelStub::create(['guid' => $guid]);

        $group->delete();

        $object = LdapGroup::create([
            'objectguid' => $guid,
            'cn' => 'Group',
        ]);

        $synchronizer = new Synchronizer(TestSynchronizerGroupModelStub::class, [
            'sync_attributes' => ['name' => 'cn'],
        ]);

        $imported = $synchronizer->run($object);

        $this->assertEquals($group->id, $imported->id);
        $this->assertTrue($imported->trashed());
    }
}

class TestSynchronizerGroupModelStub extends Model implements LdapImportable
{
    use ImportableFromLdap, SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];
}
