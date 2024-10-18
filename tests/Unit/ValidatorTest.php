<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Model as Eloquent;
use Illuminate\Support\Facades\Event;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Laravel\Auth\Validator;
use LdapRecord\Laravel\Events\Auth\RuleFailed;
use LdapRecord\Laravel\Events\Auth\RulePassed;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model as LdapRecord;

class ValidatorTest extends TestCase
{
    public function test_no_rules_exist_by_default()
    {
        $this->assertEmpty((new Validator)->getRules());
    }

    public function test_rules_can_be_added()
    {
        $rule = new TestPassingRule;
        $validator = new Validator([$rule]);

        $this->assertCount(1, $validator->getRules());
        $this->assertSame($rule, $validator->getRules()[0]);
    }

    public function test_passing_validation_rule()
    {
        Event::fake(RulePassed::class);

        $rule = new TestPassingRule;
        $this->assertTrue((new Validator([$rule]))->passes(new Entry, new TestRuleModelStub));

        Event::assertDispatched(RulePassed::class);
    }

    public function test_failing_validation_rule()
    {
        Event::fake(RuleFailed::class);

        $rule = new TestFailingRule;
        $this->assertFalse((new Validator([$rule]))->passes(new Entry, new TestRuleModelStub));

        Event::assertDispatched(RuleFailed::class);
    }

    public function test_all_rules_are_validated()
    {
        $rule = new TestPassingRule;

        $validator = new Validator([$rule]);

        $this->assertTrue($validator->passes(new Entry, new TestRuleModelStub));

        $validator->addRule(new TestFailingRule);

        $this->assertFalse($validator->passes(new Entry, new TestRuleModelStub));
    }
}

class TestRuleModelStub extends Model
{
    //
}

class TestPassingRule implements Rule
{
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        return true;
    }
}

class TestFailingRule implements Rule
{
    public function passes(LdapRecord $user, ?Eloquent $model = null): bool
    {
        return false;
    }
}
