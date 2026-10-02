<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class StudentFeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private User $student;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::create([
            'name' => 'Teacher', 'email' => 'teacher@school.com', 'password' => 'password',
            'role' => 'teacher', 'is_active' => true,
        ]);
        $this->student = User::create([
            'name' => 'Student', 'email' => 'student@school.com', 'password' => 'password',
            'role' => 'student', 'is_active' => true,
        ]);
        $this->course = Course::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Flutter Foundations',
            'learning_objectives' => "Build a widget tree\n\nUse state management\n",
            'status' => 'published',
            'is_published' => true,
            'difficulty_level' => 'beginner',
        ]);
    }

    private function lesson(array $attributes = []): Lesson
    {
        return Lesson::create(array_merge([
            'course_id' => $this->course->id,
            'title' => 'Lesson',
            'order' => 1,
            'status' => 'published',
            'availability' => 'free',
        ], $attributes));
    }

    public function test_course_returns_learning_objectives_as_a_list(): void
    {
        $this->actingAs($this->student)
            ->getJson("/api/courses/{$this->course->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.learning_objectives', ['Build a widget tree', 'Use state management']);
    }

    public function test_teacher_can_save_learning_objectives(): void
    {
        $this->actingAs($this->teacher)
            ->putJson("/api/courses/{$this->course->slug}", ['learning_objectives' => "One\nTwo"])
            ->assertStatus(200)
            ->assertJsonPath('course.learning_objectives', ['One', 'Two']);
    }

    public function test_scheduled_lesson_opens_on_its_date(): void
    {
        Enrollment::create(['user_id' => $this->student->id, 'course_id' => $this->course->id, 'status' => 'active', 'enrolled_at' => now()]);
        $future = $this->lesson(['title' => 'Future', 'availability' => 'scheduled', 'available_at' => now()->addDay()]);
        $past = $this->lesson(['title' => 'Past', 'order' => 2, 'availability' => 'scheduled', 'available_at' => now()->subDay()]);

        $this->actingAs($this->student);
        $this->getJson("/api/courses/{$this->course->id}/lessons/{$future->id}")->assertJsonPath('data.is_available', false);
        $this->getJson("/api/courses/{$this->course->id}/lessons/{$past->id}")->assertJsonPath('data.is_available', true);

        $this->putJson("/api/lessons/{$future->id}/progress", ['completed' => true])->assertStatus(422);
        $this->putJson("/api/lessons/{$past->id}/progress", ['completed' => true])->assertStatus(200);
    }

    public function test_scheduled_availability_requires_a_date(): void
    {
        $lesson = $this->lesson();

        $this->actingAs($this->teacher)
            ->putJson("/api/courses/{$this->course->slug}/lessons/{$lesson->id}/availability", ['availability' => 'scheduled'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('available_at');

        $this->putJson("/api/courses/{$this->course->slug}/lessons/{$lesson->id}/availability", [
            'availability' => 'scheduled',
            'available_at' => now()->addWeek()->toIso8601String(),
        ])->assertStatus(200)->assertJsonPath('lesson.is_available', false);
    }

    public function test_publishing_a_lesson_posts_a_course_update(): void
    {
        $lesson = $this->lesson(['title' => 'Navigation', 'status' => 'draft']);

        $this->actingAs($this->teacher)
            ->postJson("/api/courses/{$this->course->slug}/lessons/{$lesson->id}/publish")
            ->assertStatus(200);

        $this->assertDatabaseHas('announcements', [
            'course_id' => $this->course->id,
            'type' => 'course_update',
            'title' => 'New lesson: Navigation',
            'is_published' => true,
        ]);
    }

    public function test_adding_a_resource_posts_a_course_update(): void
    {
        $lesson = $this->lesson();
        $file = UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf');

        $this->actingAs($this->teacher)
            ->postJson("/api/courses/{$this->course->slug}/lessons/{$lesson->id}/resources", ['file' => $file, 'name' => 'Notes'])
            ->assertStatus(201);

        $this->assertDatabaseHas('announcements', ['title' => 'New learning material: Notes', 'type' => 'course_update']);
    }

    public function test_draft_course_does_not_post_course_updates(): void
    {
        $this->course->unpublish();

        $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->slug}/lessons", [
            'title' => 'Hidden', 'status' => 'published',
        ])->assertStatus(201);

        $this->assertSame(0, Announcement::count());
    }

    public function test_student_dashboard(): void
    {
        $first = $this->lesson(['title' => 'First']);
        $second = $this->lesson(['title' => 'Second', 'order' => 2]);
        Enrollment::create(['user_id' => $this->student->id, 'course_id' => $this->course->id, 'status' => 'active', 'enrolled_at' => now()]);

        $this->actingAs($this->student)
            ->putJson("/api/lessons/{$first->id}/progress", ['completed' => true])
            ->assertStatus(200);

        $this->getJson('/api/dashboard')
            ->assertStatus(200)
            ->assertJsonPath('role', 'student')
            ->assertJsonPath('summary.enrolled_courses', 1)
            ->assertJsonPath('summary.completed_lessons', 1)
            ->assertJsonPath('continue_learning.next_lesson.id', $second->id)
            ->assertJsonPath('recent_activity.0.lesson_title', 'First')
            ->assertJsonStructure(['courses', 'recent_announcements']);
    }
}
