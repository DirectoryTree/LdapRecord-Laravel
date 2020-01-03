<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\LdapUserRepository;
use LdapRecord\Laravel\Scopes\ScopeInterface;
use LdapRecord\Models\Entry;
use LdapRecord\Query\Model\Builder;

class LdapUserRepositoryTest extends TestCase
{
    public function test_model_can_be_created()
    {
        $model = (new LdapUserRepository(Entry::class))->createModel();
        $this->assertInstanceOf(Entry::class, $model);
    }

    public function test_query_can_be_created()
    {
        $repository = new LdapUserRepository(Entry::class);
        $query = $repository->query();
        $this->assertInstanceOf(Builder::class, $query);
        $this->assertInstanceOf(Entry::class, $query->getModel());
    }

    public function test_query_selects_are_merged()
    {
        $repository = new LdapUserRepository(Entry::class);
        $this->assertEquals(['*', 'objectguid', 'objectclass'], $repository->query()->getSelects());
    }

    public function test_query_scopes_are_applied()
    {
        $repository = new LdapUserRepository(Entry::class, [TestLdapUserRepositoryScope::class]);
        $this->assertEquals(['applied', 'objectclass'], $repository->query()->getSelects());
    }
}

class TestLdapUserRepositoryScope implements ScopeInterface
{
    public function apply(Builder $builder)
    {
        $builder->select('applied');
    }
}
