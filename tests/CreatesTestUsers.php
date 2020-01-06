<?php

namespace LdapRecord\Laravel\Tests;

trait CreatesTestUsers
{
    protected function createTestUser(array $attributes = [])
    {
        return tap(new TestUser, function (TestUser $user) use ($attributes) {
            foreach ($attributes as $name => $value) {
                $user->{$name} = $value;
            }

            $user->save();
        });
    }
}
