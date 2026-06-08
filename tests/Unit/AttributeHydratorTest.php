<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Import\Hydrators\AttributeHydrator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class AttributeHydratorTest extends TestCase
{
    public function test_attribute_hydrator()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new AttributeHydratorTestModelStub;

        AttributeHydrator::with([
            'sync_attributes' => ['foo' => 'bar'],
        ])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_attribute_hydrator_can_use_handle_function_of_class()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new AttributeHydratorTestModelStub;

        AttributeHydrator::with([
            'sync_attributes' => [AttributeHydratorTestHandlerStub::class],
        ])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_attribute_hydrator_can_use_invokable_class()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new AttributeHydratorTestModelStub;

        AttributeHydrator::with(['sync_attributes' => [
            AttributeHydratorTestInvokableStub::class,
        ]])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }

    public function test_attribute_hydrator_can_use_inline_function()
    {
        $entry = new Entry(['bar' => 'baz']);
        $model = new AttributeHydratorTestModelStub;

        AttributeHydrator::with(['sync_attributes' => [
            function ($object, $eloquent) {
                $eloquent->foo = $object->getFirstAttribute('bar');
            },
        ]])->hydrate($entry, $model);

        $this->assertEquals('baz', $model->foo);
    }
}

class AttributeHydratorTestModelStub extends Model
{
    //
}

class AttributeHydratorTestHandlerStub
{
    public function handle($object, $eloquent)
    {
        $eloquent->foo = $object->getFirstAttribute('bar');
    }
}

class AttributeHydratorTestInvokableStub
{
    public function __invoke($object, $eloquent)
    {
        $eloquent->foo = $object->getFirstAttribute('bar');
    }
}
