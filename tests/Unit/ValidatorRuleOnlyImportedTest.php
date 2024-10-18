<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use LdapRecord\Laravel\Auth\Rules\OnlyImported;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\Models\Entry;

class ValidatorRuleOnlyImportedTest extends TestCase
{
    public function test_only_imported()
    {
        $this->assertFalse(
            (new OnlyImported)->passes(new Entry, new TestNonExistingOnlyImportedRuleModelStub)
        );

        $this->assertTrue(
            (new OnlyImported)->passes(new Entry, new TestExistingOnlyImportedRuleModelStub)
        );
    }
}

class TestNonExistingOnlyImportedRuleModelStub extends Model
{
    public $exists = false;
}

class TestExistingOnlyImportedRuleModelStub extends Model
{
    public $exists = true;
}
