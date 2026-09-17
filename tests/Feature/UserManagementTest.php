<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@school.com',
            'password' => 'password',
            'role' => 'administrator',
            'is_active' => true,
        ]);

        $this->teacher = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@school.com',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_list_users(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'email', 'role', 'is_active'],
                ],
            ]);
    }

    public function test_teacher_cannot_list_users(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->getJson('/api/users');

        $response->assertStatus(403);
    }

    public function test_admin_can_create_user(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/users', [
            'name' => 'New Student',
            'email' => 'student@school.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'student',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'User created successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'student@school.com',
            'role' => 'student',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_view_user_details(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson("/api/users/{$this->teacher->id}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $this->teacher->id,
                    'name' => 'Teacher User',
                    'email' => 'teacher@school.com',
                ],
            ]);
    }

    public function test_admin_can_update_user(): void
    {
        $this->actingAs($this->admin);

        $response = $this->putJson("/api/users/{$this->teacher->id}", [
            'name' => 'Updated Teacher',
            'email' => 'updated_teacher@school.com',
            'role' => 'teacher',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User updated successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->teacher->id,
            'name' => 'Updated Teacher',
            'email' => 'updated_teacher@school.com',
        ]);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/users/{$this->teacher->id}/toggle-status");

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'User deactivated successfully.',
            ]);

        $this->assertFalse($this->teacher->fresh()->is_active);

        // Reactivate
        $response2 = $this->postJson("/api/users/{$this->teacher->id}/toggle-status");
        $response2->assertStatus(200)
            ->assertJson([
                'message' => 'User activated successfully.',
            ]);

        $this->assertTrue($this->teacher->fresh()->is_active);
    }

    public function test_admin_cannot_deactivate_self(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson("/api/users/{$this->admin->id}/toggle-status");

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'You cannot deactivate your own account.',
            ]);
    }
}
