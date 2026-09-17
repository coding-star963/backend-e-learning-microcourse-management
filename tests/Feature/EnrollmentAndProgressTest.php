<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentAndProgressTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Course $course;
    private Lesson $lesson;

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

        $category = Category::create([
            'name' => 'Mobile Dev',
        ]);

        $this->course = Course::create([
            'teacher_id' => $this->teacher->id,
            'category_id' => $category->id,
            'title' => 'Flutter Foundations',
            'status' => 'published',
            'is_published' => true,
            'difficulty_level' => 'beginner',
        ]);

        $this->lesson = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Flutter Widgets',
            'order' => 1,
            'status' => 'published',
        ]);
    }

    public function test_teacher_can_enroll_student_in_course(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->postJson('/api/enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Student enrolled successfully.',
            ]);

        $this->assertDatabaseHas('enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
        ]);
    }

    public function test_cannot_enroll_student_twice(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'progress' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($this->teacher);

        $response = $this->postJson('/api/enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'message' => 'This student is already enrolled in this course.',
            ]);
    }

    public function test_teacher_can_view_enrollment_stats(): void
    {
        Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'progress' => 50,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($this->teacher);

        $response = $this->getJson('/api/enrollments/stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total',
                'active',
                'completed',
                'cancelled',
                'suspended',
                'average_progress',
            ]);
    }

    public function test_student_progress_monitoring(): void
    {
        $enrollment = Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'progress' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($this->teacher);

        $response = $this->getJson("/api/progress/student/{$this->student->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'student' => ['id', 'name', 'email'],
                'summary' => ['total_courses', 'completed_courses', 'average_progress'],
                'courses',
            ]);
    }

    public function test_toggle_lesson_progress(): void
    {
        $enrollment = Enrollment::create([
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
            'status' => 'active',
            'progress' => 0,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($this->student);

        $response = $this->putJson("/api/lessons/{$this->lesson->id}/progress", [
            'enrollment_id' => $enrollment->id,
            'completed' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'completed' => true,
        ]);

        $this->assertEquals(100, $enrollment->fresh()->progress);
        $this->assertEquals('completed', $enrollment->fresh()->status);
    }
}
