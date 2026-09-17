<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementAndDashboardTest extends TestCase
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

    public function test_teacher_can_create_and_publish_announcement(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->postJson('/api/announcements', [
            'title' => 'Midterm Exam Schedule',
            'content' => 'The exam will take place next Monday.',
            'type' => 'important',
            'is_published' => true,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Announcement created successfully.',
            ]);

        $this->assertDatabaseHas('announcements', [
            'title' => 'Midterm Exam Schedule',
            'type' => 'important',
            'is_published' => true,
        ]);
    }

    public function test_user_can_view_notification_history(): void
    {
        Announcement::create([
            'user_id' => $this->teacher->id,
            'title' => 'Published Notification',
            'content' => 'Notification content',
            'type' => 'general',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->actingAs($this->teacher);

        $response = $this->getJson('/api/announcements/history');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'title', 'content', 'type', 'is_published'],
                ],
            ]);
    }

    public function test_admin_dashboard_metrics(): void
    {
        $this->actingAs($this->admin);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'role',
                'summary' => [
                    'total_users',
                    'total_students',
                    'total_teachers',
                    'total_courses',
                    'published_courses',
                    'draft_courses',
                    'archived_courses',
                    'total_enrollments',
                    'active_enrollments',
                    'completed_enrollments',
                    'average_progress',
                    'total_lessons',
                    'total_announcements',
                ],
                'recent_courses',
                'recent_enrollments',
                'recent_announcements',
            ])
            ->assertJson([
                'role' => 'administrator',
            ]);
    }

    public function test_teacher_dashboard_metrics(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->getJson('/api/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'role',
                'summary' => [
                    'total_courses',
                    'published_courses',
                    'draft_courses',
                    'archived_courses',
                    'total_enrollments',
                    'active_enrollments',
                    'completed_enrollments',
                    'average_progress',
                    'total_students',
                    'total_lessons',
                ],
                'recent_courses',
                'recent_enrollments',
            ])
            ->assertJson([
                'role' => 'teacher',
            ]);
    }
}
