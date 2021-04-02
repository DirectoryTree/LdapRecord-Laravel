<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Auth\Rule;
use LdapRecord\Laravel\Auth\Validator;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class ValidatorTest extends TestCase
{
    public function test_no_rules_exist_by_default()
    {
        $this->assertEmpty((new Validator)->getRules());
    }

    public function test_rules_can_be_added()
    {
        $rule = new TestPassingRule(new Entry, new TestRuleModelStub);
        $validator = new Validator([$rule]);

        $this->assertCount(1, $validator->getRules());
        $this->assertSame($rule, $validator->getRules()[0]);
    }

    public function test_passing_validation_rule()
    {
        $rule = new TestPassingRule(new Entry, new TestRuleModelStub);
        $this->assertTrue((new Validator([$rule]))->passes());
    }

    public function test_failing_validation_rule()
    {
        $rule = new TestFailingRule(new Entry, new TestRuleModelStub);
        $this->assertFalse((new Validator([$rule]))->passes());
    }

    public function test_all_rules_are_validated()
    {
        $rule = new TestPassingRule(new Entry, new TestRuleModelStub);

        $validator = new Validator([$rule]);

        $this->assertTrue($validator->passes());

        $validator->addRule(new TestFailingRule(new Entry, new TestRuleModelStub));

        $this->assertFalse($validator->passes());
    }
}

class TestRuleModelStub extends Model
{
    //
}

class TestPassingRule extends Rule
{
    public function isValid()
    {
        return true;
    }
}

class TestFailingRule extends Rule
{
    public function isValid()
    {
        return false;
    }
}
