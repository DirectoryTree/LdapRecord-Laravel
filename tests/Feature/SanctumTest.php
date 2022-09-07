<?php

namespace LdapRecord\Laravel\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\PersonalAccessToken;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Validation\ValidationException;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

class SanctumTest extends DatabaseTestCase
{
    use CreatesTestUsers;
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setupDatabaseUserProvider([
            'database' => [
                'model' => SanctumTestUserModelStub::class,
                'sync_attributes' => [
                    'name' => 'cn',
                    'email' => 'mail',
                ],
            ]
        ]);

        Route::get('api/user', function (Request $request) {
            return $request->user();
        })->middleware('auth:sanctum');

        Route::post('api/sanctum/token', function (Request $request) {
            if (Auth::validate($request->only('mail', 'password'))) {
                return ['token' => Auth::getLastAttempted()->createToken($request->device_name)->plainTextToken];
            }

            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        });
    }

    public function testLdapUserCanRequestSanctumToken()
    {
        $fake = DirectoryEmulator::setup();

        $user = LdapUser::create([
            'cn' => 'John Doe',
            'mail' => 'john@local.com',
        ]);

        $fake->actingAs($user);

        $this->postJson('api/sanctum/token', [
            'mail' => 'john@local.com',
            'password' => 'secret',
            'device_name' => 'browser',
        ])->assertJsonStructure(['token']);

        $this->assertDatabaseHas(SanctumTestUserModelStub::class, [
            'email' => $user->mail[0],
            'name' => $user->cn[0],
        ]);

        $this->assertDatabaseCount(PersonalAccessToken::class, 1);
    }

    public function testLdapUserCanFailRequestingSanctumTokenWithInvalidPassword()
    {
        DirectoryEmulator::setup();

        $user = LdapUser::create([
            'cn' => 'John Doe',
            'mail' => 'john@local.com',
        ]);

        $this->postJson('api/sanctum/token', [
            'mail' => 'john@local.com',
            'password' => 'secret',
            'device_name' => 'browser',
        ])->assertJsonValidationErrors(['email' => 'The provided credentials are incorrect.']);

        $this->assertDatabaseMissing(SanctumTestUserModelStub::class, [
            'email' => $user->mail[0],
            'name' => $user->cn[0],
        ]);

        $this->assertDatabaseCount(PersonalAccessToken::class, 0);
    }

    public function testLdapUserCanUseSanctumTokenForAuthentication()
    {
        $fake = DirectoryEmulator::setup();

        $user = LdapUser::create([
            'cn' => 'John Doe',
            'mail' => 'john@local.com',
        ]);

        $fake->actingAs($user);

        $plainTextToken = $this->postJson('api/sanctum/token', [
            'mail' => 'john@local.com',
            'password' => 'secret',
            'device_name' => 'browser',
        ])->json('token');

        $this->getJson('api/user', [
            'Authorization' => "Bearer $plainTextToken",
        ])->assertJsonFragment([
            'name' => $user->cn[0],
            'email' => $user->mail[0],
        ]);
    }
}
