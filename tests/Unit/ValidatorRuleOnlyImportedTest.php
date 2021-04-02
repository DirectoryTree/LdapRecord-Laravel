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
            (new OnlyImported(new Entry, new TestNonExistingOnlyImportedRuleModelStub))->isValid()
        );

        $this->assertTrue(
            (new OnlyImported(new Entry, new TestExistingOnlyImportedRuleModelStub))->isValid()
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
