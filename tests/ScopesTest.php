<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Connection;
use LdapRecord\Laravel\Scopes\MemberOfScope;
use LdapRecord\Laravel\Scopes\UidScope;
use LdapRecord\Laravel\Scopes\UpnScope;
use LdapRecord\Query\Model\Builder;

class ScopesTest extends TestCase
{
    public function test_member_of_scope()
    {
        $query = new Builder(new Connection);
        (new MemberOfScope)->apply($query);
        $this->assertEquals(['objectclass', 'memberof'], $query->getSelects());

        $query = new Builder(new Connection);
        $query->select('additional');
        (new MemberOfScope)->apply($query);
        $this->assertEquals(['additional', 'objectclass', 'memberof'], $query->getSelects());
    }

    public function test_uid_scope()
    {
        $query = new Builder(new Connection);
        (new UidScope)->apply($query);
        $this->assertEquals(['field' => 'uid', 'operator' => '*', 'value' => ''], $query->filters['and'][0]);
    }

    public function test_upn_scope()
    {
        $query = new Builder(new Connection);
        (new UpnScope)->apply($query);
        $this->assertEquals(['field' => 'userprincipalname', 'operator' => '*', 'value' => ''], $query->filters['and'][0]);
    }
}
