<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            'teacher_id' => $admin->id]);
        $this->assertFalse(Schema::hasColumn('courses', 'section'));
    }

    public function test_course_can_be_created_and_edited_without_block_or_section(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->postJson('/api/courses', [
            'title' => 'General Skills', 'difficulty_level' => 'beginner',
        ])->assertCreated();
        $course = Course::findOrFail($response->json('course.id'));

        $this->putJson('/api/courses/'.$course->slug, [
            'title' => 'Everyday Skills', 'status' => 'published',
        ])->assertOk()->assertJsonPath('course.title', 'Everyday Skills');

        $this->assertDatabaseHas('courses', [
            'id' => $course->id, 'is_published' => true,
        ]);
    }

    public function test_obsolete_section_input_does_not_restrict_course_editing_or_learner_access(): void
    {
        $admin = $this->admin();
        $course = Course::create(['teacher_id' => $admin->id, 'title' => 'Community Skills',
            'difficulty_level' => 'beginner', 'status' => 'published', 'is_published' => true]);

        $response = $this->actingAs($admin)->putJson('/api/courses/'.$course->slug, [
            'description' => 'Skills for everyone.', 'section' => 'Block B', 'block' => 'B',
        ])->assertOk();
        $this->assertArrayNotHasKey('section', $response->json('course'));
        $this->assertArrayNotHasKey('block', $response->json('course'));
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'description' => 'Skills for everyone.']);
        $this->assertFalse(Schema::hasColumn('courses', 'section'));

        $learner = User::factory()->create(['role' => 'student']);
        $catalog = $this->actingAs($learner)->getJson('/api/courses')->assertOk();
        $catalog->assertJsonPath('data.0.id', $course->id);
        $this->assertArrayNotHasKey('section', $catalog->json('data.0'));
        $detail = $this->getJson('/api/courses/'.$course->slug)->assertOk();
        $this->assertArrayNotHasKey('section', $detail->json('data'));
        $this->postJson('/api/enrollments', ['course_id' => $course->id])->assertCreated();
    }

    public function test_section_migration_removes_legacy_grouping_and_preserves_courses(): void
    {
        $course = Course::create(['teacher_id' => $this->admin()->id, 'title' => 'Existing Course',
            'difficulty_level' => 'beginner']);
        $migration = require database_path('migrations/2026_10_24_000001_remove_section_from_courses_table.php');
        $migration->down();
        DB::table('courses')->where('id', $course->id)->update(['section' => 'Block A']);

        $migration->up();

        $this->assertFalse(Schema::hasColumn('courses', 'section'));
        $this->assertDatabaseHas('courses', ['id' => $course->id, 'title' => 'Existing Course']);
    }

    public function test_demo_data_seeds_general_courses_and_enrolled_learners(): void
    {
        $this->seed();

        $this->assertFalse(Schema::hasColumn('courses', 'section'));
        $this->assertFalse(Schema::hasColumn('courses', 'block'));
        $this->assertDatabaseHas('users', ['email' => 'admin.microlearn@gmail.com', 'role' => 'administrator']);
        $this->assertDatabaseMissing('users', ['role' => 'teacher']);
        $this->assertGreaterThan(0, Course::where('is_published', true)->count());
        $this->assertSame(0, Course::whereHas('teacher', fn ($query) => $query->where('role', '!=', 'administrator'))->count());
        $this->assertGreaterThan(0, User::where('role', 'student')->count());
        $this->assertSame(0, User::where('role', 'student')->doesntHave('enrollments')->count());
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
