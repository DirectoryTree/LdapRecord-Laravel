<?php

namespace LdapRecord\Laravel\Tests\Unit;

use LdapRecord\Laravel\LdapAuthServiceProvider;
use LdapRecord\Laravel\Tests\TestCase;

class LdapAuthServiceProviderTest extends TestCase
{
    public function test_migrations_are_publishable()
    {
        $this->artisan('vendor:publish', ['--provider' => LdapAuthServiceProvider::class, '--no-interaction' => true]);

        $migrationFile = database_path('migrations/'.date('Y_m_d_His', time()).'_add_ldap_columns_to_users_table.php');

        $this->assertFileExists($migrationFile);

        unlink($migrationFile);

        $this->assertFileDoesNotExist($migrationFile);
    }
}
