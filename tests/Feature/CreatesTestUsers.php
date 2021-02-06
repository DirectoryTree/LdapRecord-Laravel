<?php

namespace LdapRecord\Laravel\Tests\Feature;

trait CreatesTestUsers
{
    /**
     * Create a new test user.
     *
     * @param array       $attributes
     * @param string|null $model
     *
     * @return TestUserModelStub|\Illuminate\Database\Eloquent\Model
     */
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
