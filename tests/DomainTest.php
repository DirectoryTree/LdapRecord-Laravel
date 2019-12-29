<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Connection;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\DomainAuthenticator;
use LdapRecord\Laravel\DomainUserLocator;
use LdapRecord\Laravel\Validation\Validator;
use LdapRecord\Models\ActiveDirectory\User;

class DomainTest extends TestCase
{
    public function test_name_can_be_set()
    {
        $this->assertEquals('foo', (new Domain('foo'))->getName());
    }

    public function test_auto_connect_should_default_to_true()
    {
        $this->assertTrue((new Domain('foo'))->shouldAutoConnect());
    }

    public function test_config_should_be_empty_by_default()
    {
        $this->assertEmpty((new Domain('foo'))->getConfig());
    }

    public function test_auth_username_should_be_set_by_default()
    {
        $this->assertNotEmpty((new Domain('foo'))->getAuthUsername());
    }

    public function test_auth_scopes_and_rules_should_be_empty_by_default()
    {
        $domain = new Domain('foo');
        $this->assertEmpty($domain->getAuthRules());
        $this->assertEmpty($domain->getAuthScopes());
    }

    public function test_domains_do_not_have_connection_by_default()
    {
        $this->assertNull((new Domain('foo'))->getConnection());
    }

    public function test_ldap_model_should_be_active_directory_user_by_default()
    {
        $this->assertEquals(User::class, (new Domain('foo'))->getLdapModel());
    }

    public function test_domains_can_create_connections_with_their_configuration()
    {
        $domain = new Domain('foo');
        $this->assertInstanceOf(Connection::class, $domain->getNewConnection());
    }

    public function test_domain_connections_can_be_set()
    {
        $domain = new Domain('foo');
        $this->assertNull($domain->getConnection());

        $conn = new Connection(['hosts' => ['foo', 'bar']]);
        $domain->setConnection($conn);
        $this->assertEquals(['foo', 'bar'], $domain->getConnection()->getConfiguration()->get('hosts'));
    }

    public function test_authenticator_can_be_created()
    {
        $domain = new Domain('foo');
        $this->assertInstanceOf(DomainAuthenticator::class, $domain->auth());
    }

    public function test_user_locator_can_be_created()
    {
        $domain = new Domain('foo');
        $this->assertInstanceOf(DomainUserLocator::class, $domain->locate());
    }

    public function test_user_validator_can_be_created()
    {
        $domain = new Domain('foo');
        $ldapModel = $domain->getLdapModel();
        $this->assertInstanceOf(Validator::class, $domain->userValidator(new $ldapModel));
    }
}
