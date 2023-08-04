<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use LdapRecord\Laravel\Import\Importer;
use LdapRecord\Laravel\ImportableFromLdap;
use LdapRecord\Laravel\LdapImportable;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\ActiveDirectory\Group as LdapGroup;
use LdapRecord\Models\Model as LdapModel;
use LdapRecord\Models\Scope;
use LdapRecord\Query\Model\Builder;

class LdapImporterTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_importer_group_model_stubs', function (Blueprint $table) {
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
        Schema::dropIfExists('test_importer_group_model_stubs');

        parent::tearDown();
    }

    public function test_class_based_import_works()
    {
        $object = LdapGroup::create([
            'objectguid' => $this->faker->uuid,
            'cn' => 'Group',
        ]);

        $imported = (new Importer)
            ->setLdapModel(LdapGroup::class)
            ->setEloquentModel(TestImporterGroupModelStub::class)
            ->setSyncAttributes(['name' => 'cn'])
            ->execute();

        $this->assertCount(1, $imported);
        $this->assertTrue($imported->first()->exists);
        $this->assertEquals($object->getFirstAttribute('cn'), $imported->first()->name);
    }

    public function test_class_based_import_can_have_callable_importer()
    {
        $object = LdapGroup::create([
            'objectguid' => $this->faker->uuid,
            'cn' => 'Group',
        ]);

        $imported = (new Importer)
            ->setLdapModel(LdapGroup::class)
            ->setEloquentModel(TestImporterGroupModelStub::class)
            ->syncAttributesUsing(function ($object, $database) {
                $database
                    ->forceFill(['name' => $object->getFirstAttribute('cn')])
                    ->save();
            })->execute();

        $this->assertCount(1, $imported);
        $this->assertEquals($object->getFirstAttribute('cn'), $imported->first()->name);
    }

    public function test_scopes_can_be_applied_to_import_query()
    {
        LdapGroup::create([
            'objectguid' => $this->faker->uuid,
            'cn' => 'First Group',
        ]);

        LdapGroup::create([
            'objectguid' => $this->faker->uuid,
            'cn' => 'Second Group',
        ]);

        $imported = (new Importer)
            ->setLdapModel(LdapGroup::class)
            ->setSyncAttributes(['name' => 'cn'])
            ->setLdapScopes(TestImporterScopeStub::class)
            ->setEloquentModel(TestImporterGroupModelStub::class)
            ->execute();

        $this->assertCount(1, $imported);
        $this->assertEquals('Second Group', $imported->first()->name);
    }
}

class TestImporterScopeStub implements Scope
{
    public function apply(Builder $query, LdapModel $model): void
    {
        $query->where('cn', 'Second Group');
    }
}

class TestImporterGroupModelStub extends Model implements LdapImportable
{
    use ImportableFromLdap, SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];
}
