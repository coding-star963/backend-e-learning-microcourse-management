<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_login(): void
    {
        $user = User::create([
            'name' => 'Admin',
            'email' => 'admin@gmail.com',
            'password' => 'password',
            'role' => 'administrator',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'admin@gmail.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_teacher_can_login(): void
    {
        $user = User::create([
            'name' => 'Teacher',
            'email' => 'teacher@gmail.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'teacher@gmail.com',
            'password' => 'password',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'role'],
                'token',
            ]);

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        User::create([
            'name' => 'User',
            'email' => 'user@gmail.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'user@gmail.com',
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_unauthenticated_users_cannot_access_protected_resources(): void
    {
        $response = $this->getJson('/api/user');

        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->postJson('/api/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out.',
            ]);
    }

    public function test_logout_only_revokes_the_current_device(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'two-devices@example.com',
            'password' => 'password',
            'role' => 'student',
        ]);
        $webToken = $user->createToken('auth-token')->plainTextToken;
        $mobileToken = $user->createToken('auth-token')->plainTextToken;

        $this->withToken($webToken)->postJson('/api/logout')->assertStatus(200);

        $this->assertSame(1, $user->tokens()->count());
        $this->app['auth']->forgetGuards();
        $this->withToken($mobileToken)->getJson('/api/user')->assertStatus(200);
    }

    public function test_user_can_view_profile(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name' => 'User',
                    'email' => 'user@example.com',
                    'role' => 'teacher',
                ],
            ]);
    }

    public function test_user_can_update_profile(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/profile', [
            'name' => 'Updated Name',
            'email' => 'updated@gmail.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Profile updated successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@gmail.com',
        ]);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password updated successfully.',
            ]);

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_password_update_requires_current_password(): void
    {
        $user = User::create([
            'name' => 'User',
            'email' => 'user@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/api/profile/password', [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertStatus(422);
    }

    public function test_deactivated_user_cannot_login(): void
    {
        User::create([
            'name' => 'Deactivated User',
            'email' => 'inactive@gmail.com',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => false,
            'email_verified_at' => now(),
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'inactive@gmail.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_forgot_password_returns_reset_token_for_known_email(): void
    {
        User::factory()->create(['email' => 'reset@example.com']);

        $this->postJson('/api/forgot-password', ['email' => 'reset@example.com'])
            ->assertStatus(200)
            ->assertJsonStructure(['message', 'reset_token']);

        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'reset@example.com',
        ]);
    }

    public function test_forgot_password_rejects_unknown_email(): void
    {
        $this->postJson('/api/forgot-password', ['email' => 'nobody@example.com'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingPath('reset_token');
    }

    public function test_user_can_reset_password_with_returned_token(): void
    {
        $user = User::create([
            'name' => 'Reset User',
            'email' => 'reset2@example.com',
            'password' => 'old-password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);
        $user->createToken('old-device');

        $token = $this->postJson('/api/forgot-password', [
            'email' => 'reset2@example.com',
        ])->assertStatus(200)->json('reset_token');

        $this->postJson('/api/reset-password', [
            'email' => 'reset2@example.com',
            'token' => $token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertStatus(200)
            ->assertJson(['message' => 'Your password has been reset successfully.']);

        $this->assertTrue(Hash::check('new-secure-password', $user->fresh()->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'reset2@example.com',
        ]);

        // The token works only once.
        $this->postJson('/api/reset-password', [
            'email' => 'reset2@example.com',
            'token' => $token,
            'password' => 'another-password',
            'password_confirmation' => 'another-password',
        ])->assertStatus(422);
    }

    public function test_reset_token_expires_after_fifteen_minutes(): void
    {
        User::factory()->create(['email' => 'expired@example.com']);

        $token = $this->postJson('/api/forgot-password', [
            'email' => 'expired@example.com',
        ])->json('reset_token');

        $this->travel(16)->minutes();

        $this->postJson('/api/reset-password', [
            'email' => 'expired@example.com',
            'token' => $token,
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ])->assertStatus(422)->assertJsonValidationErrors(['token']);
    }

    public function test_user_cannot_reset_password_with_invalid_token(): void
    {
        User::create([
            'name' => 'Reset User',
            'email' => 'reset3@example.com',
            'password' => 'old-password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $this->postJson('/api/forgot-password', [
            'email' => 'reset3@example.com',
        ]);

        $resetRes = $this->postJson('/api/reset-password', [
            'email' => 'reset3@example.com',
            'token' => 'invalid-token-value',
            'password' => 'new-secure-password',
            'password_confirmation' => 'new-secure-password',
        ]);

        $resetRes->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }
}