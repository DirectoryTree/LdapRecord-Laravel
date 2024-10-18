<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Database\Eloquent\Model;

trait CreatesTestUsers
{
    /**
     * Create a new test user.
     */
    protected function createTestUser(array $attributes = [], ?string $model = null): Model
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
