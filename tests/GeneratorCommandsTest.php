<?php

namespace LdapRecord\Laravel\Tests;

class GeneratorCommandsTest extends TestCase
{
    public function test_make_ldap_model_works()
    {
        $this->artisan('make:ldap-model User')->assertExitCode(0);

        $this->assertFileExists(app_path('Ldap'.DIRECTORY_SEPARATOR.'User.php'));
    }

    public function test_make_ldap_rule_works()
    {
        $this->artisan('make:ldap-rule OnlyAdmins')->assertExitCode(0);

        $path = 'Ldap'.DIRECTORY_SEPARATOR.'Rules'.DIRECTORY_SEPARATOR.'OnlyAdmins.php';

        $this->assertFileExists(app_path($path));
    }

    public function test_make_ldap_scope_works()
    {
        $this->artisan('make:ldap-scope CompanyScope')->assertExitCode(0);

        $path = 'Ldap'.DIRECTORY_SEPARATOR.'Scopes'.DIRECTORY_SEPARATOR.'CompanyScope.php';

        $this->assertFileExists(app_path($path));
    }
}