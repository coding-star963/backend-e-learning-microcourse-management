<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'administrator']);
        $this->student = User::factory()->create(['role' => 'student']);
        $this->actingAs($this->admin);
    }

    private function course(string $title, string $status = 'published', bool $published = true): Course
    {
        return Course::create(['teacher_id' => $this->admin->id, 'title' => $title,
            'difficulty_level' => 'beginner', 'status' => $status, 'is_published' => $published]);
    }

    private function enrolled(Course $course, string $status = 'active', ?User $student = null): Enrollment
    {
        return Enrollment::create(['user_id' => ($student ?? $this->student)->id,
            'course_id' => $course->id, 'status' => $status, 'progress' => 55, 'enrolled_at' => now()]);
    }

    public function test_available_modules_exclude_every_existing_enrollment_status(): void
    {
        foreach (['active', 'completed', 'suspended', 'cancelled'] as $status) {
            $this->enrolled($this->course($status), $status);
        }
        $this->course('Draft', 'draft', false);
        $this->course('Archived', 'archived', false);
        $this->course('Not actually published', 'published', false);
        $available = $this->course('Available');
        $otherStudent = User::factory()->create(['role' => 'student']);
        $this->enrolled($available, 'active', $otherStudent);

        $this->getJson('/api/courses?available_for_enrollment=1&user_id='.$this->student->id)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $available->id);
    }

    public function test_no_remaining_modules_returns_an_empty_list_and_rejects_a_duplicate(): void
    {
        $course = $this->course('Only module');
        $enrollment = $this->enrolled($course);
        $this->getJson('/api/courses?available_for_enrollment=1&user_id='.$this->student->id)
            ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
        $this->postJson('/api/enrollments', ['user_id' => $this->student->id, 'course_id' => $course->id])
            ->assertStatus(422)->assertJsonPath('message', 'This student is already enrolled in this course.');
        $this->assertDatabaseCount('enrollments', 1);
        $this->assertEquals(55, $enrollment->fresh()->progress);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_duplicate_requests_preserve_completed_suspended_and_cancelled_records(): void
    {
        foreach (['active', 'completed', 'suspended', 'cancelled'] as $status) {
            $course = $this->course('Module '.$status);
            $enrollment = $this->enrolled($course, $status);
            $this->postJson('/api/enrollments', ['user_id' => $this->student->id, 'course_id' => $course->id])
                ->assertStatus(422);
            $this->assertSame($status, $enrollment->fresh()->status);
            $this->assertEquals(55, $enrollment->fresh()->progress);
        }
        $this->assertDatabaseCount('enrollments', 4);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_a_different_available_module_can_be_enrolled_once(): void
    {
        $this->enrolled($this->course('Already joined'));
        $course = $this->course('New module');
        $payload = ['user_id' => $this->student->id, 'course_id' => $course->id];
        $id = $this->postJson('/api/enrollments', $payload)->assertCreated()->json('enrollment.id');
        $this->postJson('/api/enrollments', $payload)->assertStatus(422);
        $this->assertDatabaseCount('enrollments', 2);
        $this->assertDatabaseHas('enrollments', ['id' => $id, 'progress' => 0]);
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_unavailable_modules_cannot_enroll_an_existing_or_new_learner(): void
    {
        foreach (['draft', 'archived'] as $status) {
            $course = $this->course($status, $status, false);
            $this->postJson('/api/enrollments', ['user_id' => $this->student->id, 'course_id' => $course->id])->assertStatus(422);
            $this->postJson('/api/enrollments', ['name' => 'New Learner', 'email' => $status.'@gmail.com',
                'password' => 'password123', 'course_id' => $course->id])->assertStatus(422);
        }
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('enrollments', 0);
    }

    public function test_filter_is_applied_before_pagination(): void
    {
        $available = $this->course('Available oldest');
        $this->enrolled($this->course('Joined recently'));
        $this->enrolled($this->course('Joined newest'));
        $this->getJson('/api/courses?available_for_enrollment=1&per_page=1&user_id='.$this->student->id)
            ->assertOk()->assertJsonPath('data.0.id', $available->id)->assertJsonPath('meta.total', 1);
    }

    public function test_new_learner_form_has_no_choices_when_nothing_is_published(): void
    {
        $this->course('Draft', 'draft', false);
        $this->getJson('/api/courses?available_for_enrollment=1')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson('/api/enrollments', ['name' => 'New Learner', 'email' => 'new@gmail.com',
            'password' => 'password123'])->assertStatus(422)->assertJsonValidationErrors('course_id');
        $this->assertDatabaseCount('users', 2);
    }

    public function test_learner_cannot_query_another_learners_enrollment_choices(): void
    {
        $this->actingAs($this->student)->getJson('/api/courses?available_for_enrollment=1&user_id='.$this->student->id)
            ->assertForbidden();
    }
}
