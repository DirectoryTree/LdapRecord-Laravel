<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\LdapImportable;
use LdapRecord\Laravel\ImportableFromLdap;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TestGroupModelStub extends Model implements LdapImportable
{
    use ImportableFromLdap, SoftDeletes;

    public $timestamps = false;

    protected $guarded = [];
}
