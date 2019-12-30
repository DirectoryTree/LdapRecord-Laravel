<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\ContainerException;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainAuthenticator;
use LdapRecord\Laravel\DomainUserLocator;
use LdapRecord\Laravel\Scopes\ScopeInterface;
use LdapRecord\Laravel\Validation\Rules\Rule;
use LdapRecord\Laravel\Validation\Validator;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Query\Model\Builder;

class DomainTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Flush container instance.
        Container::getNewInstance();
    }

    public function test_ldap_model_instance_can_be_created()
    {
        $ldapModel = Domain::ldapModel();

        $this->assertInstanceOf(Model::class, $ldapModel);
        $this->assertInstanceOf(Domain::getLdapModel(), $ldapModel);
    }

    public function test_auto_connect_can_be_set()
    {
        Domain::shouldAutoConnect(false);
        $this->assertFalse(Domain::$shouldAutoConnect);

        Domain::shouldAutoConnect(true);
        $this->assertTrue(Domain::$shouldAutoConnect);
    }

    public function test_connection_instance_can_be_created()
    {
        $connection = Domain::getNewConnection();
        $this->assertInstanceOf(Connection::class, $connection);
        $this->assertFalse($connection->isConnected());
    }

    public function test_scopes_are_empty_by_default()
    {
        $this->assertEmpty(Domain::scopes());
        $this->assertEmpty(Domain::getAuthScopes());
    }

    public function test_scope_instances_can_be_created()
    {
        $scopes = TestDomainUsingScopesStub::scopes();

        $this->assertCount(1, $scopes);
        $this->assertInstanceOf(ScopeInterface::class, $scopes[0]);
        $this->assertInstanceOf(TestDomainScopeStub::class, $scopes[0]);
    }

    public function test_rules_are_empty_by_default()
    {
        $this->assertEmpty(Domain::getAuthRules());
    }

    public function test_authenticator_instance_cannot_be_created_without_registered_connection()
    {
        $this->expectException(ContainerException::class);
        Domain::auth();
    }

    public function test_authenticator_instance_can_be_created()
    {
        Container::addConnection(Domain::getNewConnection(), Domain::class);
        $guard = Domain::auth();
        $this->assertInstanceOf(DomainAuthenticator::class, $guard);
    }

    public function test_locator_instance_can_be_created()
    {
        $locator = Domain::locate();
        $this->assertInstanceOf(DomainUserLocator::class, $locator);
    }

    public function test_locator_query_cannot_be_created_without_registered_connection()
    {
        $this->expectException(ContainerException::class);
        Domain::locate()->query();
    }

    public function test_rule_instances_can_be_created()
    {
        $rules = TestDomainUsingRulesStub::rules(new Entry());
        $this->assertCount(1, $rules);
        $this->assertInstanceOf(Rule::class, $rules[0]);
        $this->assertTrue($rules[0]->isValid());
    }

    public function test_validator_instance_can_be_created()
    {
        $validator = TestDomainUsingRulesStub::validator(new Entry());
        $this->assertInstanceOf(Validator::class, $validator);
        $this->assertTrue($validator->passes());
    }
}

class TestDomainUsingScopesStub extends Domain
{
    public static function getAuthScopes()
    {
        return [TestDomainScopeStub::class];
    }
}

class TestDomainScopeStub implements ScopeInterface
{
    public function apply(Builder $builder)
    {
        //
    }
}

class TestDomainUsingRulesStub extends Domain
{
    public static function getAuthRules()
    {
        return [TestDomainRuleStub::class];
    }
}

class TestDomainRuleStub extends Rule
{
    public function isValid()
    {
        return true;
    }
}
