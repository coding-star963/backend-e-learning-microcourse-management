<?php

namespace Tests\Feature;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $teacher;
    private User $otherTeacher;
    private User $student;
    private User $otherStudent;
    private Course $course;
    private Course $otherCourse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->makeUser('admin@school.com', 'administrator');
        $this->teacher = $this->makeUser('sarah@school.com', 'teacher');
        $this->otherTeacher = $this->makeUser('michael@school.com', 'teacher');
        $this->student = $this->makeUser('alex@gmail.com', 'student');
        $this->otherStudent = $this->makeUser('maria@gmail.com', 'student');

        $this->course = $this->makeCourse($this->teacher, 'Sarah Course');
        $this->otherCourse = $this->makeCourse($this->otherTeacher, 'Michael Course');
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

    private function makeCourse(User $teacher, string $title, ?Category $category = null): Course
    {
        return Course::create([
            'teacher_id' => $teacher->id,
            'category_id' => $category?->id,
            'title' => $title,
            'status' => 'published',
            'is_published' => true,
            'difficulty_level' => 'beginner',
        ]);
    }

    private function makeLesson(Course $course, string $title, array $attributes = []): Lesson
    {
        return Lesson::create(array_merge([
            'course_id' => $course->id,
            'title' => $title,
            'order' => Lesson::getNextOrder($course->id),
            'status' => 'published',
            'availability' => 'free',
            'video_path' => 'lessons/videos/' . str($title)->slug() . '.mp4',
        ], $attributes));
    }

    private function enroll(User $student, Course $course, string $status = 'active'): Enrollment
    {
        return Enrollment::create([
            'user_id' => $student->id,
            'course_id' => $course->id,
            'status' => $status,
            'progress' => 0,
            'enrolled_at' => now(),
        ]);
    }

    // --- Teacher isolation --------------------------------------------------

    public function test_notification_history_hides_other_teachers_course_announcements(): void
    {
        Announcement::create(['user_id' => $this->otherTeacher->id, 'course_id' => $this->otherCourse->id, 'title' => 'Michael only', 'content' => 'x', 'type' => 'general', 'is_published' => true, 'published_at' => now()]);
        Announcement::create(['user_id' => $this->admin->id, 'course_id' => null, 'title' => 'Everyone', 'content' => 'x', 'type' => 'general', 'is_published' => true, 'published_at' => now()]);
        Announcement::create(['user_id' => $this->teacher->id, 'course_id' => $this->course->id, 'title' => 'Sarah only', 'content' => 'x', 'type' => 'general', 'is_published' => true, 'published_at' => now()]);

        $titles = $this->actingAs($this->teacher)->getJson('/api/announcements/history')
            ->assertStatus(200)->json('data.*.title');

        $this->assertEqualsCanonicalizing(['Everyone', 'Sarah only'], $titles);

        $this->actingAs($this->admin)->getJson('/api/announcements/history')->assertJsonCount(3, 'data');
    }

    public function test_only_administrators_can_change_or_remove_shared_categories(): void
    {
        $category = Category::create(['name' => 'Programming']);

        $this->actingAs($this->teacher);
        $this->postJson('/api/categories', ['name' => 'Design'])->assertStatus(201);
        $this->putJson("/api/categories/{$category->slug}", ['name' => 'Hijacked'])->assertStatus(403);
        $this->deleteJson("/api/categories/{$category->slug}")->assertStatus(403);

        $this->actingAs($this->admin);
        $this->putJson("/api/categories/{$category->slug}", ['name' => 'Coding'])->assertStatus(200);
        $this->deleteJson('/api/categories/coding')->assertStatus(200);
    }

    public function test_category_course_counts_only_include_the_teachers_own_courses(): void
    {
        $category = Category::create(['name' => 'Programming']);
        $this->course->update(['category_id' => $category->id]);
        $this->otherCourse->update(['category_id' => $category->id]);

        $this->actingAs($this->teacher)->getJson('/api/categories')
            ->assertJsonPath('data.0.courses_count', 1);
        $this->actingAs($this->admin)->getJson('/api/categories')
            ->assertJsonPath('data.0.courses_count', 2);
    }

    public function test_progress_student_list_is_scoped_to_the_teachers_courses(): void
    {
        $this->enroll($this->student, $this->course);
        $this->enroll($this->otherStudent, $this->otherCourse);

        $this->actingAs($this->teacher)->getJson('/api/progress/students')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email', 'alex@gmail.com');

        $this->actingAs($this->admin)->getJson('/api/progress/students')->assertJsonCount(2, 'data');
        $this->actingAs($this->student)->getJson('/api/progress/students')->assertStatus(403);
    }

    public function test_enrollable_students_only_count_the_teachers_own_enrollments(): void
    {
        $this->enroll($this->otherStudent, $this->otherCourse);

        $students = collect($this->actingAs($this->teacher)->getJson('/api/enrollments/students')->json('data'));

        $this->assertSame(0, $students->firstWhere('email', 'maria@gmail.com')['enrollments_count']);
    }

    // --- Stored progress stays in step with published lessons ----------------

    public function test_publishing_a_new_lesson_reopens_a_completed_enrollment(): void
    {
        $lesson = $this->makeLesson($this->course, 'One');
        $enrollment = $this->enroll($this->student, $this->course);

        $this->actingAs($this->student)->putJson("/api/lessons/{$lesson->id}/progress", ['completed' => true])
            ->assertStatus(200);
        $this->assertSame('completed', $enrollment->fresh()->status);

        $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->slug}/lessons", [
            'title' => 'Two',
            'status' => 'published',
            'is_free_preview' => '0',
        ])->assertStatus(201);

        $enrollment->refresh();
        $this->assertSame('active', $enrollment->status);
        $this->assertEquals(50, $enrollment->progress);
        $this->assertNull($enrollment->completed_at);

        // Removing the new lesson again completes the course.
        $two = Lesson::where('title', 'Two')->first();
        $this->deleteJson("/api/courses/{$this->course->slug}/lessons/{$two->id}")->assertStatus(200);

        $enrollment->refresh();
        $this->assertSame('completed', $enrollment->status);
        $this->assertEquals(100, $enrollment->progress);
    }

    public function test_unpublishing_a_lesson_recalculates_progress(): void
    {
        $one = $this->makeLesson($this->course, 'One');
        $this->makeLesson($this->course, 'Two');
        $enrollment = $this->enroll($this->student, $this->course);
        LessonProgress::create(['user_id' => $this->student->id, 'lesson_id' => $one->id, 'enrollment_id' => $enrollment->id, 'completed' => true, 'completed_at' => now()]);
        $enrollment->recalculateProgress();
        $this->assertEquals(50, $enrollment->fresh()->progress);

        $two = Lesson::where('title', 'Two')->first();
        $this->actingAs($this->teacher)->postJson("/api/courses/{$this->course->slug}/lessons/{$two->id}/unpublish")
            ->assertStatus(200);

        $this->assertEquals(100, $enrollment->fresh()->progress);
        $this->assertSame('completed', $enrollment->fresh()->status);
    }

    // --- Lesson media is only sent to students who can open the lesson ------

    public function test_locked_lessons_do_not_expose_media_to_students(): void
    {
        $open = $this->makeLesson($this->course, 'Open');
        $locked = $this->makeLesson($this->course, 'Locked', ['availability' => 'locked']);
        $later = $this->makeLesson($this->course, 'Later', ['availability' => 'scheduled', 'available_at' => now()->addDay()]);
        $this->enroll($this->student, $this->course);

        $this->actingAs($this->student);
        $lessons = collect($this->getJson("/api/courses/{$this->course->slug}/lessons")->json('data'))->keyBy('title');

        $this->assertNotNull($lessons['Open']['video_url']);
        $this->assertNull($lessons['Locked']['video_url']);
        $this->assertNull($lessons['Later']['video_url']);
        $this->assertNull($this->getJson("/api/courses/{$this->course->slug}/lessons/{$locked->id}")->json('data.video_url'));
        $this->assertNotNull($this->getJson("/api/courses/{$this->course->slug}/lessons/{$open->id}")->json('data.video_url'));

        // The teacher still sees everything.
        $this->actingAs($this->teacher)
            ->getJson("/api/courses/{$this->course->slug}/lessons/{$later->id}")
            ->assertJsonPath('data.video_url', fn ($url) => $url !== null);
    }

    public function test_students_who_are_not_enrolled_only_get_free_preview_media(): void
    {
        $this->makeLesson($this->course, 'Preview', ['is_free_preview' => true]);
        $this->makeLesson($this->course, 'Members');

        $lessons = collect($this->actingAs($this->student)
            ->getJson("/api/courses/{$this->course->slug}/lessons")->json('data'))->keyBy('title');

        $this->assertNotNull($lessons['Preview']['video_url']);
        $this->assertNull($lessons['Members']['video_url']);
    }

    // --- Accounts ------------------------------------------------------------

    public function test_admin_cannot_change_their_own_role(): void
    {
        $this->actingAs($this->admin)
            ->putJson("/api/users/{$this->admin->id}", ['role' => 'teacher'])
            ->assertStatus(422);

        $this->assertSame('administrator', $this->admin->fresh()->role);
    }

    public function test_cancelled_student_is_told_to_contact_their_teacher(): void
    {
        $this->enroll($this->student, $this->course, 'cancelled');

        $this->actingAs($this->student)
            ->postJson('/api/enrollments', ['course_id' => $this->course->id])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Your enrollment in this course was cancelled. Please contact your teacher.');
    }
}
