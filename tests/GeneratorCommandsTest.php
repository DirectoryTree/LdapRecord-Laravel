<?php

namespace LdapRecord\Laravel\Tests;

class GeneratorCommandsTest extends TestCase
{
    public function test_make_ldap_model_works()
    {
        $this->artisan('make:ldap-model User')->assertExitCode(0);
        $this->assertFileExists(base_path('App/Ldap/User.php'));
    }

    public function test_make_ldap_rule_works()
    {
        $this->artisan('make:ldap-rule OnlyAdmins')->assertExitCode(0);
        $this->assertFileExists(base_path('App/Ldap/Rules/OnlyAdmins.php'));
    }

    public function test_make_ldap_scope_works()
    {
        $this->artisan('make:ldap-scope CompanyScope')->assertExitCode(0);
        $this->assertFileExists(base_path('App/Ldap/Scopes/CompanyScope.php'));
    }
}
