<?php

namespace LdapRecord\Laravel\Tests;

use LdapRecord\Laravel\Commands\Importer;
use LdapRecord\Laravel\Facades\Resolver;
use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Tests\Models\TestUser;

class EloquentAuthenticateTest extends DatabaseTestCase
{
    /** @test */
    public function it_does_not_set_the_ldap_user_if_the_auth_provider_is_not_adldap()
    {
        $this->app['config']->set('auth.guards.web.provider', 'users');

        $user = $this->makeLdapUser([
            'objectguid'        => ['cc07cacc-5d9d-fa40-a9fb-3a4d50a172b0'],
            'cn'                => ['John Doe'],
            'userprincipalname' => ['jdoe@email.com'],
        ]);

        $importer = new Importer($user, new TestUser());

        $model = $importer->run();

        Resolver::spy();
        Resolver::shouldNotReceive('byModel');

        Auth::login($model);
    }
}
