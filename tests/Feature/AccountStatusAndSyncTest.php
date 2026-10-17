<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Account suspension, Super Admin self-protection, per-student enrollment
 * notices, live sync and dashboard account statistics.
 */
class AccountStatusAndSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $otherTeacher;
    private User $student;
    private Course $course;
    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@gmail.com', 'administrator');
        $this->teacher = $this->makeUser('teacher@gmail.com', 'teacher');
        $this->otherTeacher = $this->makeUser('other.teacher@gmail.com', 'teacher');
        $this->student = $this->makeUser('student@gmail.com', 'student');

        $this->course = Course::create([
            'teacher_id' => $this->teacher->id,
            'title' => 'Flutter Foundations',
            'status' => 'published',
            'is_published' => true,
            'difficulty_level' => 'beginner',
        ]);
        $this->lesson = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Widgets',
            'order' => 1,
            'status' => 'published',
        ]);
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst(strtok($email, '@')),
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function enroll(User $student, string $status = 'active'): Enrollment
    {
        return Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $this->course->id,
            'status' => $status,
            'enrolled_at' => now(),
        ]);
    }

    /**
     * Each simulated request starts signed out, as a real request would.
     */
    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->app['auth']->shouldUse('web');
    }

    private function noticeActions(User $student): array
    {
        return $student->fresh()->notifications()->get()->pluck('data.action')->all();
    }

    // --- Account suspension -------------------------------------------------

    public function test_a_suspended_student_is_blocked_from_the_app_with_a_clear_message(): void
    {
        $this->enroll($this->student);
        $token = $this->student->createToken('mobile')->plainTextToken;

        $this->actingAs($this->teacher)
            ->postJson("/api/users/{$this->student->id}/suspend")
            ->assertOk()
            ->assertJsonPath('user.account_status', 'suspended');
        $this->resetAuth();

        // An open session is refused on the next request, with the reason.
        $this->withToken($token)->getJson('/api/sync')
            ->assertStatus(403)
            ->assertJsonPath('account_status', 'suspended')
            ->assertJsonPath('message', User::SUSPENDED_MESSAGE);
        $this->resetAuth();

        // Signing in again is refused with the same message.
        $this->postJson('/api/login', ['email' => 'student@gmail.com', 'password' => 'password'])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', User::SUSPENDED_MESSAGE);
        $this->resetAuth();

        // Reactivating restores access.
        $this->actingAs($this->admin)
            ->postJson("/api/users/{$this->student->id}/unsuspend")
            ->assertOk()
            ->assertJsonPath('user.account_status', 'active');
        $this->resetAuth();

        $this->postJson('/api/login', ['email' => 'student@gmail.com', 'password' => 'password'])
            ->assertOk();
        $this->assertContains('account_reactivated', $this->noticeActions($this->student));
    }

    public function test_teachers_can_only_suspend_students_in_their_own_courses(): void
    {
        $this->actingAs($this->teacher)
            ->postJson("/api/users/{$this->student->id}/suspend")
            ->assertForbidden();

        $this->enroll($this->student);

        $this->actingAs($this->otherTeacher)
            ->postJson("/api/users/{$this->student->id}/suspend")
            ->assertForbidden();
        $this->actingAs($this->teacher)
            ->postJson("/api/users/{$this->otherTeacher->id}/suspend")
            ->assertForbidden();
        $this->actingAs($this->teacher)
            ->postJson("/api/users/{$this->student->id}/suspend")
            ->assertOk();

        $this->assertTrue($this->student->fresh()->isSuspended());
    }

    public function test_activate_and_deactivate_clear_a_suspension(): void
    {
        $this->student->update(['suspended_at' => now()]);
        $this->actingAs($this->admin);

        $this->postJson("/api/users/{$this->student->id}/toggle-status")
            ->assertJsonPath('user.account_status', 'deactivated');
        $this->postJson("/api/users/{$this->student->id}/suspend")->assertStatus(422);
        $this->postJson("/api/users/{$this->student->id}/toggle-status")
            ->assertJsonPath('user.account_status', 'active');
    }

    public function test_suspended_students_cannot_be_enrolled_by_staff(): void
    {
        $this->student->update(['suspended_at' => now()]);

        $this->actingAs($this->teacher)->postJson('/api/enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ])->assertStatus(422);
    }

    public function test_users_can_be_filtered_by_account_status(): void
    {
        $this->student->update(['suspended_at' => now()]);
        $this->otherTeacher->update(['is_active' => false]);

        $this->actingAs($this->admin);
        $this->getJson('/api/users?status=suspended')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->student->id);
        $this->getJson('/api/users?status=deactivated')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->otherTeacher->id);
        $this->getJson('/api/users?status=active')->assertJsonCount(2, 'data');
    }

    // --- Super Admin --------------------------------------------------------

    public function test_the_super_admin_cannot_deactivate_suspend_or_change_their_own_role(): void
    {
        $this->actingAs($this->admin);

        $this->postJson("/api/users/{$this->admin->id}/toggle-status")->assertStatus(422);
        $this->postJson("/api/users/{$this->admin->id}/suspend")->assertStatus(422);
        $this->putJson("/api/users/{$this->admin->id}", ['role' => 'teacher'])->assertStatus(422);

        $this->assertSame('active', $this->admin->fresh()->accountStatus());
        $this->assertTrue($this->admin->fresh()->isAdministrator());
    }

    // --- Enrollment notices (mobile) ---------------------------------------

    public function test_students_are_notified_of_every_enrollment_action(): void
    {
        $this->actingAs($this->teacher);

        $enrollmentId = $this->postJson('/api/enrollments', [
            'user_id' => $this->student->id,
            'course_id' => $this->course->id,
        ])->assertCreated()->json('enrollment.id');

        foreach (['suspended', 'active', 'cancelled', 'active', 'completed'] as $status) {
            $this->putJson("/api/enrollments/{$enrollmentId}", ['status' => $status])->assertOk();
        }
        // Re-sending the same status is not a change, so no extra notice.
        $this->putJson("/api/enrollments/{$enrollmentId}", ['status' => 'completed'])->assertOk();
        $this->deleteJson("/api/enrollments/{$enrollmentId}")->assertOk();

        $this->assertEqualsCanonicalizing(
            ['enrolled', 'suspended', 'reactivated', 'cancelled', 'reactivated', 'completed', 'deleted'],
            $this->noticeActions($this->student)
        );

        $this->actingAs($this->student);
        $notices = $this->getJson('/api/notifications')->assertOk()->json('data');
        $this->assertCount(7, $notices);
        $this->assertSame('enrollment', $notices[0]['type']);
        $this->assertSame('Flutter Foundations', $notices[0]['course']['title']);
        $this->assertStringStartsWith('notice-', $notices[0]['id']);
    }

    public function test_deleting_an_enrollment_removes_its_progress_and_allows_re_enrolling(): void
    {
        $enrollment = $this->enroll($this->student);
        LessonProgress::create([
            'user_id' => $this->student->id,
            'lesson_id' => $this->lesson->id,
            'enrollment_id' => $enrollment->id,
            'completed' => true,
            'completed_at' => now(),
        ]);

        $this->actingAs($this->teacher)->deleteJson("/api/enrollments/{$enrollment->id}")->assertOk();
        $this->assertDatabaseMissing('lesson_progress', ['enrollment_id' => $enrollment->id]);

        $this->actingAs($this->student);
        $this->getJson("/api/enrollments/student/{$this->student->id}")->assertJsonCount(0, 'data');
        $this->getJson("/api/enrollments/{$enrollment->id}")->assertNotFound();
        $this->postJson('/api/enrollments', ['course_id' => $this->course->id])->assertCreated();
    }

    public function test_a_cancelled_enrollment_blocks_progress_media_and_self_re_enrolling(): void
    {
        $enrollment = $this->enroll($this->student);
        $this->actingAs($this->teacher)
            ->putJson("/api/enrollments/{$enrollment->id}", ['status' => 'cancelled'])->assertOk();

        $this->actingAs($this->student);
        $this->getJson("/api/enrollments/{$enrollment->id}")->assertJsonPath('data.status', 'cancelled');
        $this->putJson("/api/lessons/{$this->lesson->id}/progress", ['completed' => true])->assertStatus(422);
        $this->postJson('/api/enrollments', ['course_id' => $this->course->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Your enrollment in this course was cancelled. Please contact your teacher.');
        $this->getJson('/api/dashboard')->assertJsonPath('summary.enrolled_courses', 0);
    }

    // --- Live sync -----------------------------------------------------------

    public function test_the_sync_version_changes_when_a_students_data_changes(): void
    {
        $enrollment = $this->enroll($this->student);
        $this->actingAs($this->student);
        $version = fn () => $this->getJson('/api/sync')->assertOk()->json('version');

        $first = $version();
        $this->assertSame($first, $version(), 'Nothing changed, so the version is stable.');

        $this->travel(2)->seconds();
        $enrollment->update(['status' => 'suspended']);
        $second = $version();
        $this->assertNotSame($first, $second);

        $enrollment->delete();
        $this->assertNotSame($second, $version());
    }

    public function test_the_sync_version_covers_files_in_every_published_lesson(): void
    {
        $second = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Layouts',
            'order' => 2,
            'status' => 'published',
        ]);
        $this->actingAs($this->student);
        $before = $this->getJson('/api/sync')->assertOk()->json('version');

        LessonResource::create([
            'lesson_id' => $second->id,
            'name' => 'Slides',
            'file_path' => 'lesson-resources/slides.pdf',
            'file_type' => 'pdf',
        ]);

        $this->assertNotSame($before, $this->getJson('/api/sync')->json('version'));
    }

    public function test_staff_sync_version_changes_when_an_account_status_changes(): void
    {
        $this->actingAs($this->admin);
        $before = $this->getJson('/api/sync')->assertOk()->json('version');

        $this->travel(2)->seconds();
        $this->student->update(['suspended_at' => now()]);

        $this->assertNotSame($before, $this->getJson('/api/sync')->json('version'));
    }

    // --- Dashboard ------------------------------------------------------------

    public function test_admin_dashboard_counts_students_and_teachers_by_account_status(): void
    {
        $this->student->update(['suspended_at' => now()]);
        $this->makeUser('second.student@gmail.com', 'student');
        $this->makeUser('third.student@gmail.com', 'student')->update(['is_active' => false]);
        $this->otherTeacher->update(['is_active' => false]);

        $this->actingAs($this->admin)->getJson('/api/dashboard')
            ->assertJsonPath('account_stats.students', ['total' => 3, 'active' => 1, 'suspended' => 1, 'deactivated' => 1])
            ->assertJsonPath('account_stats.teachers', ['total' => 2, 'active' => 1, 'suspended' => 0, 'deactivated' => 1]);
    }

    public function test_teacher_dashboard_counts_only_their_students_by_account_status(): void
    {
        $this->enroll($this->student);
        $this->student->update(['suspended_at' => now()]);
        $this->makeUser('not.mine@gmail.com', 'student');

        $this->actingAs($this->teacher)->getJson('/api/dashboard')
            ->assertJsonPath('account_stats.students', ['total' => 1, 'active' => 0, 'suspended' => 1, 'deactivated' => 0]);
    }
}
