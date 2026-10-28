<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_and_receives_token(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'name' => 'Juana Pérez',
            'email' => 'JUANA@example.com',
            'password' => 'Secreta123!',
            'password_confirmation' => 'Secreta123!',
            'role' => 'admin', // intento de escalar privilegios: debe ignorarse
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.email', 'juana@example.com')
            ->assertJsonPath('data.user.role', 'customer')
            ->assertJsonStructure(['data' => ['token', 'token_type', 'expires_at']]);
    }

    public function test_register_validates_weak_password_and_duplicate_email(): void
    {
        User::factory()->create(['email' => 'dup@example.com']);

        $this->postJson('/api/auth/register', [
            'name' => 'X',
            'email' => 'dup@example.com',
            'password' => '123',
            'password_confirmation' => '123',
        ])->assertStatus(422)
            ->assertJsonPath('error_code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_login_with_valid_and_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'ana@example.com', 'password' => 'Secreta123!']);

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'mala'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('email');

        $token = $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Secreta123!'])
            ->assertOk()
            ->json('data.token');

        $this->withToken($token)->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com');
    }

    public function test_logout_revokes_token(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('error_code', 'UNAUTHENTICATED');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'y']);
        }

        $this->postJson('/api/auth/login', ['email' => 'x@example.com', 'password' => 'y'])
            ->assertStatus(429)
            ->assertJsonPath('error_code', 'TOO_MANY_REQUESTS');
    }
}
