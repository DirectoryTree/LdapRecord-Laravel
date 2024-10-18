<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\LdapResultResponse;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;

class ListenForLdapBindFailureTest extends TestCase
{
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $app['config']->set('ldap.connections.default', [
            'hosts' => ['one', 'two', 'three'],
            'username' => 'user',
            'password' => 'secret',
            'base_dn' => 'dc=local,dc=com',
        ]);
    }

    public function test_validation_exception_is_not_thrown_until_all_connection_hosts_are_attempted()
    {
        $this->setupPlainUserProvider(['model' => User::class]);

        $fake = DirectoryFake::setup('default')->shouldNotBeConnected();

        $expectedSelects = [
            'objectguid',
            '*',
        ];

        $expectedFilter = $fake->query()
            ->where([
                ['objectclass', '=', 'top'],
                ['objectclass', '=', 'person'],
                ['objectclass', '=', 'organizationalperson'],
                ['objectclass', '=', 'user'],
                ['mail', '=', 'jdoe@local.com'],
                ['objectclass', '!=', 'computer'],
            ])
            ->getQuery();

        $expectedQueryResult = [
            [
                'mail' => ['jdoe@local.com'],
                'dn' => ['cn=jdoe,dc=local,dc=com'],
            ],
        ];

        $fake->getLdapConnection()->expect([
            // Two bind attempts fail on hosts "one" and "two" with configured user account.
            LdapFake::operation('bind')
                ->with('user', 'secret')
                ->twice()
                ->andReturn(new LdapResultResponse(1)),

            // Third bind attempt passes.
            LdapFake::operation('bind')
                ->with('user', 'secret')
                ->once()
                ->andReturn(new LdapResultResponse),

            // Bind is attempted with the authenticating user and passes.
            LdapFake::operation('bind')
                ->with('cn=jdoe,dc=local,dc=com', 'secret')
                ->once()
                ->andReturn(new LdapResultResponse),

            // Rebind is attempted with configured user account.
            LdapFake::operation('bind')
                ->with('user', 'secret')
                ->once()
                ->andReturn(new LdapResultResponse(0)),

            // Search operation is executed for authenticating user.
            LdapFake::operation('search')
                ->with(['dc=local,dc=com', $expectedFilter, $expectedSelects, false, 1])
                ->once()
                ->andReturn($expectedQueryResult),

            LdapFake::operation('parseResult')
                ->once()
                ->andReturn(new LdapResultResponse),
        ])->shouldReturnError("Can't contact LDAP server");

        $result = Auth::attempt([
            'mail' => 'jdoe@local.com',
            'password' => 'secret',
        ]);

        $this->assertTrue($result);
        $this->assertCount(2, $fake->attempted());
        $this->assertInstanceOf(User::class, Auth::user());
        $this->assertEquals(['one', 'two'], array_keys($fake->attempted()));
    }
}
