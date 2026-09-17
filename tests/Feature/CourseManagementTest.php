<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@school.com',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $this->student = User::create([
            'name' => 'Student User',
            'email' => 'student@school.com',
            'password' => 'password',
            'role' => 'student',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Web Development',
            'description' => 'Learn web technologies',
        ]);
    }

    public function test_teacher_can_list_categories(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->getJson('/api/categories');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'slug', 'description'],
                ],
            ]);
    }

    public function test_teacher_can_create_category(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->postJson('/api/categories', [
            'name' => 'Mobile Development',
            'description' => 'Flutter and iOS/Android',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Category created successfully.',
            ]);

        $this->assertDatabaseHas('categories', [
            'name' => 'Mobile Development',
            'slug' => 'mobile-development',
        ]);
    }

    public function test_teacher_can_create_course(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->postJson('/api/courses', [
            'title' => 'Introduction to Laravel',
            'description' => 'A complete beginner guide to Laravel framework.',
            'category_id' => $this->category->id,
            'difficulty_level' => 'beginner',
            'duration' => '2 hours',
            'status' => 'draft',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Course created successfully.',
            ]);

        $this->assertDatabaseHas('courses', [
            'title' => 'Introduction to Laravel',
            'slug' => 'introduction-to-laravel',
            'status' => 'draft',
            'is_published' => false,
        ]);
    }

    public function test_teacher_can_view_course_by_slug(): void
    {
        $course = Course::create([
            'teacher_id' => $this->teacher->id,
            'category_id' => $this->category->id,
            'title' => 'Vue.js Mastery',
            'description' => 'Master Vue 3',
            'difficulty_level' => 'intermediate',
            'status' => 'draft',
        ]);

        $this->actingAs($this->teacher);

        $response = $this->getJson("/api/courses/{$course->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $course->id,
                    'title' => 'Vue.js Mastery',
                    'slug' => 'vuejs-mastery',
                ],
            ]);
    }

    public function test_teacher_can_publish_unpublish_archive_course(): void
    {
        $course = Course::create([
            'teacher_id' => $this->teacher->id,
            'category_id' => $this->category->id,
            'title' => 'Lifecycle Course',
            'description' => 'Testing lifecycle',
            'difficulty_level' => 'beginner',
            'status' => 'draft',
        ]);

        $this->actingAs($this->teacher);

        // Publish
        $publishRes = $this->postJson("/api/courses/{$course->slug}/publish");
        $publishRes->assertStatus(200);
        $this->assertEquals('published', $course->fresh()->status);
        $this->assertTrue($course->fresh()->is_published);

        // Unpublish
        $unpublishRes = $this->postJson("/api/courses/{$course->slug}/unpublish");
        $unpublishRes->assertStatus(200);
        $this->assertEquals('draft', $course->fresh()->status);
        $this->assertFalse($course->fresh()->is_published);

        // Archive
        $archiveRes = $this->postJson("/api/courses/{$course->slug}/archive");
        $archiveRes->assertStatus(200);
        $this->assertEquals('archived', $course->fresh()->status);
    }

    public function test_student_cannot_create_course(): void
    {
        $this->actingAs($this->student);

        $response = $this->postJson('/api/courses', [
            'title' => 'Unauthorized Course',
            'difficulty_level' => 'beginner',
        ]);

        $response->assertStatus(403);
    }
}
