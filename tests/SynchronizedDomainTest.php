<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Database\Importer;
use LdapRecord\Laravel\Database\PasswordSynchronizer;
use LdapRecord\Laravel\Domain;
use LdapRecord\Laravel\SynchronizedDomain;

class SynchronizedDomainTest extends TestCase
{
    public function test_is_subclass_of_domain()
    {
        $this->assertInstanceOf(Domain::class, new SynchronizedDomain('foo'));
    }

    public function test_password_sync_is_disabled_by_default()
    {
        $this->assertFalse((new SynchronizedDomain('foo'))->isSyncingPasswords());
    }

    public function test_login_fallback_is_disabled_by_default()
    {
        $this->assertFalse((new SynchronizedDomain('foo'))->isFallingBack());
    }

    public function test_default_sync_attributes()
    {
        $this->assertEquals(['name' => 'cn'], (new SynchronizedDomain('foo'))->getDatabaseSyncAttributes());
    }

    public function test_default_database_model_is_default_eloquent_user()
    {
        $this->assertEquals('App\User', (new SynchronizedDomain('foo'))->getDatabaseModel());
    }

    public function test_default_database_username_column_is_email()
    {
        $this->assertEquals('email', (new SynchronizedDomain('foo'))->getDatabaseUsernameColumn());
    }

    public function test_default_database_password_column_is_password()
    {
        $this->assertEquals('password', (new SynchronizedDomain('foo'))->getDatabasePasswordColumn());
    }

    public function test_importer_can_be_created()
    {
        $this->assertInstanceOf(Importer::class, (new SynchronizedDomain('foo'))->importer());
    }

    public function test_password_synchronizer_can_be_created()
    {
        $this->assertInstanceOf(PasswordSynchronizer::class, (new SynchronizedDomain('foo'))->passwordSynchronizer());
    }
}
