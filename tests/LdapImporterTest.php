<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Schema;
use LdapRecord\Laravel\Import\Importer;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use LdapRecord\Models\ActiveDirectory\Group as LdapGroup;

class LdapImporterTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('test_group_model_stubs', function (Blueprint $table) {
            $table->increments('id');
            $table->softDeletes();
            $table->string('guid')->unique()->nullable();
            $table->string('domain')->nullable();
            $table->string('name')->nullable();
        });

        DirectoryEmulator::setup();
    }

    public function test_class_based_import_works()
    {
        $object = LdapGroup::create([
            'objectguid' => $this->faker->uuid,
            'cn' => 'Group',
        ]);

        $imported = (new Importer)
            ->setLdapModel(LdapGroup::class)
            ->setEloquentModel(TestGroupModelStub::class)
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
            ->setEloquentModel(TestGroupModelStub::class)
            ->syncAttributesUsing(function ($object, $database) {
                $database
                    ->forceFill(['name' => $object->getFirstAttribute('cn')])
                    ->save();
            })->execute();

        $this->assertCount(1, $imported);
        $this->assertEquals($object->getFirstAttribute('cn'), $imported->first()->name);
    }
}
