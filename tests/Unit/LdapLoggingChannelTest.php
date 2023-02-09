<?php

namespace LdapRecord\Laravel\Tests\Unit;

use Illuminate\Log\Logger;
use LdapRecord\Container;
use LdapRecord\Laravel\Tests\TestCase;

class LdapLoggingChannelTest extends TestCase
{
    protected function getEnvironmentSetup($app)
    {
        parent::getEnvironmentSetup($app);

        $app['config']->set('ldap.logging', true);
        $app['config']->set('ldap.logging_channel', 'stack');
    }

    public function test_log_channel_can_be_set()
    {
        $this->assertInstanceOf(Logger::class, Container::getInstance()->getLogger());
    }
}
