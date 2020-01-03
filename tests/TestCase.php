<?php

namespace LdapRecord\Laravel\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use LdapRecord\Laravel\LdapAuthServiceProvider;
use LdapRecord\Laravel\LdapServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        Hash::setRounds(4);

        return $app;
    }

    protected function getPackageProviders($app)
    {
        return [
            LdapServiceProvider::class,
            LdapAuthServiceProvider::class,
        ];
    }

    /**
     * Define the environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     */
    protected function getEnvironmentSetup($app)
    {
        $config = $app['config'];

        // Database setup.
        $config->set('database.default', 'testbench');
        $config->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
        ]);

        $config->set('ldap.default', 'default');
        $config->set('ldap.connections.default', [
            'hosts' => ['localhost'],
            'username' => 'user',
            'password' => 'secret',
            'base_dn' => 'dc=local,dc=com',
            'port' => 389,
        ]);

        // Auth setup.
        $config->set('auth.guards.web.provider', 'ldap');
        $config->set('auth.providers', [
            'ldap' => [
                'driver' => 'ldap',
                'rules' => [],
                'model' => \LdapRecord\Models\ActiveDirectory\User::class,
                'database' => [
                    'sync_passwords' => true,
                    'sync_attributes' => [
                        'name' => 'cn',
                        'email' => 'userprincipalname',
                    ],
                    'model' => \LdapRecord\Laravel\Tests\TestUser::class,
                ],
            ],
        ]);

        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            // Additional fields for LdapRecord.
            $table->string('guid')->unique()->nullable();
            $table->string('domain')->nullable();
        });
    }
}
