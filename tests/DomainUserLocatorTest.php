<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Connection;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainModelFactory;
use LdapRecord\Laravel\DomainUserLocator;
use LdapRecord\Laravel\Scopes\ScopeInterface;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;
use Mockery as m;

class DomainUserLocatorTest extends TestCase
{
    public function test_can_create_query()
    {
        $domain = new Domain('test');

        $locator = new DomainUserLocator($domain);

        $model = m::mock(Model::class);
        $model->shouldReceive('newQuery')->once()->andReturn(new Builder(new Connection));
        $model->shouldReceive('getGuidKey')->once()->andReturn('objectguid');

        $factory = m::mock(DomainModelFactory::class);
        $factory->shouldReceive('createForDomain')->withArgs([$domain])->once()->andReturn($model);

        app()->instance(DomainModelFactory::class, $factory);

        $query = $locator->query();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals(['*', 'objectguid', 'objectclass'], $query->getSelects());
    }

    public function test_scopes_are_applied_to_query()
    {
        $domain = new TestDomainWithScopes('test');

        $locator = new DomainUserLocator($domain);

        $model = m::mock(Model::class);
        $model->shouldReceive('newQuery')->once()->andReturn(new Builder(new Connection));
        $model->shouldReceive('getGuidKey')->once()->andReturn('objectguid');

        $factory = m::mock(DomainModelFactory::class);
        $factory->shouldReceive('createForDomain')->withArgs([$domain])->once()->andReturn($model);

        app()->instance(DomainModelFactory::class, $factory);

        $query = $locator->query();

        $this->assertEquals(['attribute', 'objectclass'], $query->getSelects());
    }
}

class TestDomainWithScopes extends Domain
{
    public function getAuthScopes(): array
    {
        return [TestDomainScope::class];
    }
}

class TestDomainScope implements ScopeInterface
{
    public function apply(Builder $builder)
    {
        $builder->select('attribute');
    }
}
