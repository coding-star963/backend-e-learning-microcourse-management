<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OpenLearningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'administrator']);
    }

    public function test_learner_without_school_or_section_can_register_and_enroll(): void
    {
        $course = Course::create(['teacher_id' => $this->admin()->id, 'title' => 'Community Skills',
            'difficulty_level' => 'beginner', 'status' => 'published', 'is_published' => true]);
        $registration = $this->postJson('/api/register', ['name' => 'Community Learner',
            'email' => 'community.learner@gmail.com', 'password' => 'password123',
            'password_confirmation' => 'password123', 'role' => 'teacher'])->assertCreated();
        $registration->assertJsonPath('user.role', 'student');
        $learner = User::findOrFail($registration->json('user.id'));
        $this->actingAs($learner)->getJson('/api/courses')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/enrollments', ['course_id' => $course->id])->assertCreated();
        $this->assertDatabaseHas('enrollments', ['user_id' => $learner->id, 'course_id' => $course->id]);
    }

    public function test_administrator_creates_courses_without_teacher_or_section(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->postJson('/api/courses', [
            'title' => 'Open Learning', 'difficulty_level' => 'beginner',
            'teacher_id' => 99999, 'section' => 'Obsolete block',
        ])->assertCreated();
        $this->assertArrayNotHasKey('teacher', $response->json('course'));
        $this->assertArrayNotHasKey('section', $response->json('course'));
        $this->assertDatabaseHas('courses', ['id' => $response->json('course.id'),
            'teacher_id' => $admin->id, 'section' => null]);
    }

    public function test_teacher_role_cannot_be_created_or_assigned(): void
    {
        $this->actingAs($this->admin())->postJson('/api/users', [
            'name' => 'Retired Role', 'email' => 'retired@gmail.com',
            'password' => 'password123', 'password_confirmation' => 'password123', 'role' => 'teacher',
        ])->assertUnprocessable()->assertJsonValidationErrors('role');
        $learner = User::factory()->create(['role' => 'student']);
        $this->putJson('/api/users/'.$learner->id, ['role' => 'teacher'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->getJson('/api/teachers')->assertNotFound();
    }

    public function test_former_course_owner_cannot_manage_content_after_becoming_a_learner(): void
    {
        $owner = User::factory()->create(['role' => 'student']);
        $course = Course::create(['teacher_id' => $owner->id, 'title' => 'Historical Course',
            'difficulty_level' => 'beginner', 'status' => 'draft']);
        $this->actingAs($owner)->getJson('/api/courses/'.$course->slug)->assertForbidden();
        $this->putJson('/api/courses/'.$course->slug, ['title' => 'Changed'])->assertForbidden();
        $this->postJson('/api/courses/'.$course->slug.'/lessons', ['title' => 'Changed'])->assertForbidden();
        $this->actingAs($this->admin())->putJson('/api/courses/'.$course->slug, ['title' => 'Managed by Admin'])->assertOk();
    }

    public function test_migration_preserves_courses_and_converts_teachers_without_promoting_them(): void
    {
        $former = User::factory()->create(['role' => 'teacher']);
        $former->createToken('old-portal');
        $course = Course::create(['teacher_id' => $former->id, 'title' => 'Existing Course',
            'difficulty_level' => 'beginner']);
        $migration = require database_path('migrations/2026_10_21_000001_open_learning_roles.php');
        $migration->up();
        $this->assertSame('student', $former->fresh()->role);
        $this->assertSame(0, $former->tokens()->count());
        $this->assertNotNull($course->fresh());
        $this->assertFalse($course->isManageableBy($former->fresh()));
    }

    public function test_uploaded_thumbnail_is_saved_returned_and_replaced(): void
    {
        Storage::fake('public');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII=');
        $response = $this->actingAs($this->admin())->postJson('/api/courses', [
            'title' => 'With Thumbnail', 'difficulty_level' => 'beginner',
            'thumbnail' => UploadedFile::fake()->createWithContent('cover.png', $png),
        ])->assertCreated();
        $course = Course::findOrFail($response->json('course.id'));
        $oldPath = $course->thumbnail;
        Storage::disk('public')->assertExists($oldPath);
        $this->assertSame(url('/api/media/'.$oldPath), $response->json('course.thumbnail'));
        $this->postJson('/api/courses/'.$course->slug, ['_method' => 'PUT',
            'thumbnail' => UploadedFile::fake()->createWithContent('new-cover.png', $png),
        ])->assertOk();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($course->fresh()->thumbnail);
    }

    public function test_thumbnail_urls_accept_stored_paths_and_external_images(): void
    {
        foreach (['course-thumbnails/cover.jpg', 'storage/course-thumbnails/cover.jpg', '/storage/course-thumbnails/cover.jpg'] as $path) {
            $this->assertSame(url('/api/media/course-thumbnails/cover.jpg'), (new Course(['thumbnail' => $path]))->thumbnail_url);
        }
        $external = 'https://images.example.com/cover.jpg';
        $this->assertSame($external, (new Course(['thumbnail' => $external]))->thumbnail_url);
        $this->assertNull((new Course)->thumbnail_url);
    }
}
