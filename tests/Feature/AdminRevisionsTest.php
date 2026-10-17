<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRevisionsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $student;
    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create(['name' => 'Admin', 'email' => 'admin@school.com', 'password' => 'password', 'role' => 'administrator', 'is_active' => true]);
        $this->teacher = User::create(['name' => 'Teacher', 'email' => 'teacher@school.com', 'password' => 'password', 'role' => 'teacher', 'is_active' => true]);
        $this->student = User::create(['name' => 'Student', 'email' => 'student@school.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        $this->category = Category::create(['name' => 'Web Development', 'description' => 'Web things']);
    }

    private function makeCourse(): Course
    {
        return Course::create([
            'teacher_id' => $this->teacher->id,
            'category_id' => $this->category->id,
            'title' => 'HTML Basics',
            'difficulty_level' => 'beginner',
            'status' => 'published',
            'is_published' => true,
        ]);
    }

    public function test_category_with_courses_can_be_deleted_and_courses_become_uncategorized(): void
    {
        $course = $this->makeCourse();
        $this->actingAs($this->admin);

        $this->deleteJson("/api/categories/{$this->category->slug}")->assertStatus(200);

        $this->assertDatabaseMissing('categories', ['id' => $this->category->id]);
        $this->assertNull($course->fresh()->category_id);
    }

    public function test_admin_can_assign_teacher_and_section_when_creating_course(): void
    {
        $this->actingAs($this->admin);

        $response = $this->postJson('/api/courses', [
            'title' => 'Assigned Course',
            'difficulty_level' => 'beginner',
            'teacher_id' => $this->teacher->id,
            'section' => 'BSIT 2-A',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('course.teacher.id', $this->teacher->id)
            ->assertJsonPath('course.section', 'BSIT 2-A');
    }

    public function test_course_teacher_must_have_teacher_role(): void
    {
        $this->actingAs($this->admin);

        $this->postJson('/api/courses', [
            'title' => 'Bad Teacher',
            'difficulty_level' => 'beginner',
            'teacher_id' => $this->student->id,
        ])->assertStatus(422)->assertJsonValidationErrors('teacher_id');
    }

    public function test_admin_can_list_teachers(): void
    {
        $this->makeCourse();
        $this->actingAs($this->admin);

        $this->getJson('/api/teachers')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.courses_count', 1);
    }

    public function test_teacher_cannot_access_teacher_management(): void
    {
        $this->actingAs($this->teacher);

        $this->getJson('/api/teachers')->assertStatus(403);
    }

    public function test_admin_can_register_and_enroll_new_student(): void
    {
        $course = $this->makeCourse();
        $this->actingAs($this->admin);

        $this->postJson('/api/enrollments', [
            'course_id' => $course->id,
            'name' => 'New Learner',
            'email' => 'new.learner@gmail.com',
            'password' => 'password123',
        ])->assertStatus(201);

        $newStudent = User::where('email', 'new.learner@gmail.com')->first();
        $this->assertSame('student', $newStudent->role);
        $this->assertDatabaseHas('enrollments', ['user_id' => $newStudent->id, 'course_id' => $course->id]);
    }

    public function test_only_students_can_be_enrolled(): void
    {
        $course = $this->makeCourse();
        $this->actingAs($this->admin);

        $this->postJson('/api/enrollments', [
            'course_id' => $course->id,
            'user_id' => $this->teacher->id,
        ])->assertStatus(422)->assertJsonValidationErrors('user_id');
    }

    public function test_teacher_can_list_enrollable_students(): void
    {
        $this->actingAs($this->teacher);

        $this->getJson('/api/enrollments/students')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_dashboard_learner_count_only_includes_enrolled_students(): void
    {
        $course = $this->makeCourse();
        User::create(['name' => 'Idle', 'email' => 'idle@school.com', 'password' => 'password', 'role' => 'student', 'is_active' => true]);
        Enrollment::create(['user_id' => $this->student->id, 'course_id' => $course->id, 'status' => 'active', 'progress' => 0, 'enrolled_at' => now()]);
        $this->actingAs($this->admin);

        $this->getJson('/api/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('summary.total_students', 1);
    }
}
