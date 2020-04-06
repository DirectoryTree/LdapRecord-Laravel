<?php

namespace LdapRecord\Laravel\Tests;

trait CreatesTestUsers
{
    protected function createTestUser(array $attributes = [], $model = null)
    {
        $model = $model ?? TestUserModelStub::class;

        return tap(new $model, function (TestUserModelStub $user) use ($attributes) {
            foreach ($attributes as $name => $value) {
                $user->{$name} = $value;
            }

            $user->save();
        });
    }
}
