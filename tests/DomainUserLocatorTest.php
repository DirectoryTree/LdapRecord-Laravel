<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Connection;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainUserLocator;
use LdapRecord\Laravel\Scopes\ScopeInterface;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;
use Mockery as m;

class DomainUserLocatorTest extends TestCase
{
    public function test_can_create_query()
    {
        $domain = new class extends Domain {
            public static function ldapModel()
            {
                $model = m::mock(Model::class);
                $model->shouldReceive('newQuery')->once()->andReturn(new Builder(new Connection));
                $model->shouldReceive('getGuidKey')->once()->andReturn('objectguid');

                return $model;
            }
        };

        $locator = new DomainUserLocator($domain);

        $query = $locator->query();

        $this->assertInstanceOf(Builder::class, $query);
        $this->assertEquals(['*', 'objectguid', 'objectclass'], $query->getSelects());
    }

    public function test_scopes_are_applied_to_query()
    {
        $domain = new class extends Domain {
            public static function ldapModel()
            {
                $model = m::mock(Model::class);
                $model->shouldReceive('newQuery')->once()->andReturn(new Builder(new Connection));
                $model->shouldReceive('getGuidKey')->once()->andReturn('objectguid');

                return $model;
            }

            public static function scopes()
            {
                return [
                    new class implements ScopeInterface {
                        public function apply(Builder $builder)
                        {
                            $builder->select('attribute');
                        }
                    },
                ];
            }
        };

        $locator = new DomainUserLocator($domain);

        $this->assertEquals(['attribute', 'objectclass'], $locator->query()->getSelects());
    }
}
