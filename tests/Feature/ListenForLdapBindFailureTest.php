<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Support\Facades\Auth;
use LdapRecord\Laravel\Tests\TestCase;
use LdapRecord\LdapResultResponse;
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
        $this->setupPlainUserProvider();

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
                ['objectclass', '!=', 'computer'],
                ['mail', '=', 'jdoe@local.com'],
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

            // Search operation is executed for authenticating user.
            LdapFake::operation('search')
                ->with(['dc=local,dc=com', $expectedFilter, $expectedSelects, false, 1])
                ->andReturn($expectedQueryResult),

            LdapFake::operation('parseResult')
                ->andReturn(new LdapResultResponse),

            // Downstream authentication may bind the discovered user and rebind the configured user.
            LdapFake::operation('bind')
                ->with(fn ($username) => in_array($username, ['cn=jdoe,dc=local,dc=com', 'user']), 'secret')
                ->andReturn(new LdapResultResponse),

            LdapFake::operation('bind')
                ->with(fn ($username) => in_array($username, ['cn=jdoe,dc=local,dc=com', 'user']), 'secret')
                ->andReturn(new LdapResultResponse(0)),
        ])->shouldReturnError("Can't contact LDAP server");

        $result = Auth::attempt([
            'mail' => 'jdoe@local.com',
            'password' => 'secret',
        ]);

        $this->assertIsBool($result);
        $this->assertCount(2, $fake->attempted());
        $this->assertEquals(['one', 'two'], array_keys($fake->attempted()));
    }
}
