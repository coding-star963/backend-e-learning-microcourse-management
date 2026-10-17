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

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $otherTeacher;
    private User $student;
    private User $otherStudent;
    private Course $course;
    private Course $draftCourse;
    private Course $otherCourse;
    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@school.com', 'administrator');
        $this->teacher = $this->makeUser('teacher@school.com', 'teacher');
        $this->otherTeacher = $this->makeUser('other.teacher@school.com', 'teacher');
        $this->student = $this->makeUser('student@school.com', 'student');
        $this->otherStudent = $this->makeUser('other.student@school.com', 'student');

        $this->course = $this->makeCourse($this->teacher, 'Flutter Foundations', 'published');
        $this->draftCourse = $this->makeCourse($this->teacher, 'Draft Course', 'draft');
        $this->otherCourse = $this->makeCourse($this->otherTeacher, 'Other Teacher Course', 'published');

        $this->lesson = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Widgets',
            'order' => 1,
            'status' => 'published',
            'availability' => 'free',
        ]);
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' ' . $email,
            'email' => $email,
            'password' => 'password',
            'role' => $role,
            'is_active' => true,
        ]);
    }

    private function makeCourse(User $teacher, string $title, string $status): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'title' => $title,
            'status' => $status,
            'is_published' => $status === 'published',
            'difficulty_level' => 'beginner',
        ]);
    }

    private function enroll(User $student, Course $course): Enrollment
    {
        return Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => 'active',
            'progress' => 0,
            'enrolled_at' => now(),
        ]);
    }

    // --- Routes / bugs -----------------------------------------------------

    public function test_course_categories_endpoint_works(): void
    {
        $this->actingAs($this->teacher)->getJson('/api/courses/categories')->assertStatus(200);
    }

    public function test_courses_with_the_same_title_get_unique_slugs(): void
    {
        $copy = $this->makeCourse($this->teacher, 'Flutter Foundations', 'draft');

        $this->assertSame('flutter-foundations', $this->course->slug);
        $this->assertSame('flutter-foundations-2', $copy->slug);
    }

    public function test_course_can_be_fetched_by_numeric_id(): void
    {
        $this->actingAs($this->student)
            ->getJson("/api/courses/{$this->course->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.slug', 'flutter-foundations');
    }

    public function test_lesson_must_belong_to_the_course_in_the_url(): void
    {
        $this->actingAs($this->admin)
            ->getJson("/api/courses/{$this->otherCourse->slug}/lessons/{$this->lesson->id}")
            ->assertStatus(404);
    }

    // --- Course ownership & visibility ------------------------------------

    public function test_teacher_cannot_manage_another_teachers_course(): void
    {
        $this->actingAs($this->otherTeacher);

        $this->putJson("/api/courses/{$this->course->slug}", ['title' => 'Hijacked'])->assertStatus(403);
        $this->postJson("/api/courses/{$this->course->slug}/archive")->assertStatus(403);
        $this->deleteJson("/api/courses/{$this->course->slug}")->assertStatus(403);
        $this->postJson("/api/courses/{$this->course->slug}/lessons", ['title' => 'X'])->assertStatus(403);

        $this->assertDatabaseHas('courses', ['id' => $this->course->id, 'status' => 'published']);
    }

    public function test_teacher_cannot_view_another_teachers_course_or_lessons(): void
    {
        $this->actingAs($this->otherTeacher);

        $this->getJson("/api/courses/{$this->course->slug}")->assertStatus(403);
        $this->getJson("/api/courses/{$this->course->slug}/lessons")->assertStatus(403);
        $this->getJson("/api/courses/{$this->course->slug}/lessons/{$this->lesson->id}")->assertStatus(403);
    }

    public function test_course_lessons_only_include_that_courses_lessons(): void
    {
        Lesson::create([
            'course_id' => $this->otherCourse->id,
            'title' => 'Other Lesson',
            'order' => 1,
            'status' => 'published',
            'availability' => 'free',
        ]);

        $this->actingAs($this->admin)
            ->getJson("/api/courses/{$this->course->slug}/lessons")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Widgets');
    }

    public function test_admin_can_manage_any_course(): void
    {
        $this->actingAs($this->admin)
            ->postJson("/api/courses/{$this->course->slug}/archive")
            ->assertStatus(200);
    }

    public function test_teacher_course_list_only_shows_own_courses(): void
    {
        $this->actingAs($this->teacher)
            ->getJson('/api/courses')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    public function test_student_only_sees_published_courses(): void
    {
        $this->actingAs($this->student);

        $this->getJson('/api/courses')->assertStatus(200)->assertJsonCount(2, 'data');
        $this->getJson('/api/courses?status=draft')->assertJsonCount(0, 'data');
        $this->getJson("/api/courses/{$this->draftCourse->slug}")->assertStatus(403);
    }

    public function test_student_only_sees_published_lessons_with_completion_flag(): void
    {
        Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Unfinished draft',
            'order' => 2,
            'status' => 'draft',
        ]);

        $this->actingAs($this->student)
            ->getJson("/api/courses/{$this->course->id}/lessons")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_completed', false);
    }

    public function test_resource_upload_rejects_scripts(): void
    {
        $file = UploadedFile::fake()->create('shell.php', 1, 'application/x-php');

        $this->actingAs($this->teacher)
            ->postJson("/api/courses/{$this->course->slug}/lessons/{$this->lesson->id}/resources", ['file' => $file])
            ->assertStatus(422)
            ->assertJsonValidationErrors('file');
    }

    // --- Enrollment --------------------------------------------------------

    public function test_student_cannot_list_or_manage_enrollments(): void
    {
        $enrollment = $this->enroll($this->otherStudent, $this->course);
        $this->actingAs($this->student);

        $this->getJson('/api/enrollments')->assertStatus(403);
        $this->getJson('/api/enrollments/stats')->assertStatus(403);
        $this->getJson("/api/enrollments/{$enrollment->id}")->assertStatus(403);
        $this->getJson("/api/enrollments/student/{$this->otherStudent->id}")->assertStatus(403);
        $this->putJson("/api/enrollments/{$enrollment->id}", ['progress' => 100])->assertStatus(403);
    }

    public function test_student_can_self_enroll_only_in_published_courses(): void
    {
        $this->actingAs($this->student);

        // user_id is ignored: students always enroll themselves.
        $this->postJson('/api/enrollments', [
            'course_id' => $this->course->id,
            'user_id' => $this->otherStudent->id,
        ])->assertStatus(201);

        $this->assertDatabaseHas('enrollments', ['user_id' => $this->student->id, 'course_id' => $this->course->id]);
        $this->assertDatabaseMissing('enrollments', ['user_id' => $this->otherStudent->id]);

        $this->postJson('/api/enrollments', ['course_id' => $this->draftCourse->id])->assertStatus(422);
        $this->postJson('/api/enrollments', ['course_id' => $this->course->id])->assertStatus(422);
    }

    public function test_student_cannot_create_accounts_through_enrollment(): void
    {
        $this->actingAs($this->student)->postJson('/api/enrollments', [
            'course_id' => $this->course->id,
            'name' => 'Fake',
            'email' => 'fake@school.com',
            'password' => 'password123',
        ]);

        $this->assertDatabaseMissing('users', ['email' => 'fake@school.com']);
    }

    public function test_student_enrollments_include_completed_lessons(): void
    {
        $this->enroll($this->student, $this->course);
        $this->actingAs($this->student);
        $this->putJson("/api/lessons/{$this->lesson->id}/progress", ['completed' => true])->assertStatus(200);

        $this->getJson("/api/enrollments/student/{$this->student->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.0.completed_lesson_ids', [$this->lesson->id])
            ->assertJsonPath('data.0.course.lesson_ids', [$this->lesson->id]);
    }

    public function test_teacher_cannot_enroll_students_in_another_teachers_course(): void
    {
        $this->actingAs($this->otherTeacher)->postJson('/api/enrollments', [
            'course_id' => $this->course->id,
            'user_id' => $this->student->id,
        ])->assertStatus(403);
    }

    // --- Progress ----------------------------------------------------------

    public function test_student_cannot_view_another_students_progress(): void
    {
        $this->actingAs($this->student);

        $this->getJson("/api/progress/student/{$this->otherStudent->id}")->assertStatus(403);
        $this->getJson("/api/progress/history/{$this->otherStudent->id}")->assertStatus(403);
        $this->getJson("/api/progress/course/{$this->course->slug}")->assertStatus(403);
        $this->getJson("/api/progress/student/{$this->student->id}")->assertStatus(200);
    }

    public function test_progress_ignores_draft_lessons_and_counts_completed_courses(): void
    {
        Lesson::create(['course_id' => $this->course->id, 'title' => 'Draft', 'order' => 2, 'status' => 'draft']);
        $enrollment = $this->enroll($this->student, $this->course);

        // No enrollment_id needed: the student's own enrollment is used.
        $this->actingAs($this->student)
            ->putJson("/api/lessons/{$this->lesson->id}/progress", ['completed' => true])
            ->assertStatus(200);

        $this->assertEquals(100, $enrollment->fresh()->progress);
        $this->assertSame('completed', $enrollment->fresh()->status);

        $this->getJson("/api/progress/student/{$this->student->id}")
            ->assertJsonPath('summary.completed_courses', 1);

        // Un-completing the lesson re-opens the course.
        $this->putJson("/api/lessons/{$this->lesson->id}/progress", ['completed' => false])->assertStatus(200);
        $this->assertSame('active', $enrollment->fresh()->status);
    }

    public function test_locked_lessons_cannot_be_completed(): void
    {
        $this->enroll($this->student, $this->course);
        $this->lesson->update(['availability' => 'locked']);

        $this->actingAs($this->student)
            ->putJson("/api/lessons/{$this->lesson->id}/progress", ['completed' => true])
            ->assertStatus(422);
    }

    public function test_progress_requires_enrollment(): void
    {
        $this->actingAs($this->student)
            ->putJson("/api/lessons/{$this->lesson->id}/progress", ['completed' => true])
            ->assertStatus(403);
    }

    // --- Announcements -----------------------------------------------------

    public function test_student_only_sees_published_announcements_for_their_courses(): void
    {
        $this->enroll($this->student, $this->course);

        Announcement::create(['user_id' => $this->teacher->id, 'title' => 'General', 'content' => 'x', 'is_published' => true, 'published_at' => now()]);
        Announcement::create(['user_id' => $this->teacher->id, 'course_id' => $this->course->id, 'title' => 'Mine', 'content' => 'x', 'is_published' => true, 'published_at' => now()]);
        Announcement::create(['user_id' => $this->teacher->id, 'course_id' => $this->course->id, 'title' => 'Draft', 'content' => 'x', 'is_published' => false]);
        $other = Announcement::create(['user_id' => $this->otherTeacher->id, 'course_id' => $this->otherCourse->id, 'title' => 'Other', 'content' => 'x', 'is_published' => true, 'published_at' => now()]);

        $this->actingAs($this->student);
        $this->getJson('/api/announcements')->assertStatus(200)->assertJsonCount(2, 'data');
        $this->getJson("/api/announcements/{$other->id}")->assertStatus(404);
    }

    public function test_teacher_cannot_edit_another_teachers_announcement(): void
    {
        $announcement = Announcement::create(['user_id' => $this->teacher->id, 'title' => 'Mine', 'content' => 'x']);

        $this->actingAs($this->otherTeacher);
        $this->putJson("/api/announcements/{$announcement->id}", ['title' => 'Changed'])->assertStatus(403);
        $this->deleteJson("/api/announcements/{$announcement->id}")->assertStatus(403);
        $this->postJson('/api/announcements', [
            'title' => 'Wrong course',
            'content' => 'x',
            'course_id' => $this->course->id,
        ])->assertStatus(403);
    }

    // --- Deactivated accounts ----------------------------------------------

    public function test_deactivating_a_user_revokes_their_tokens(): void
    {
        $this->teacher->createToken('auth-token');

        $this->actingAs($this->admin)
            ->postJson("/api/users/{$this->teacher->id}/toggle-status")
            ->assertStatus(200);

        $this->assertSame(0, $this->teacher->tokens()->count());
    }

    public function test_deactivated_user_is_rejected_on_protected_routes(): void
    {
        $this->teacher->update(['is_active' => false]);

        $this->actingAs($this->teacher->fresh())->getJson('/api/user')->assertStatus(401);
    }
}
