<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LessonManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->teacher = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@school.com',
            'password' => 'password',
            'role' => 'teacher',
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Full Stack',
        ]);

        $this->course = Course::create([
            'teacher_id' => $this->teacher->id,
            'category_id' => $category->id,
            'title' => 'Mastering React',
            'description' => 'React deep dive',
            'difficulty_level' => 'intermediate',
            'status' => 'published',
            'is_published' => true,
        ]);
    }

    public function test_teacher_can_create_lesson(): void
    {
        $this->actingAs($this->teacher);

        $response = $this->postJson("/api/courses/{$this->course->slug}/lessons", [
            'title' => 'Lesson 1: React Fundamentals',
            'description' => 'Hooks and state',
            'availability' => 'free',
            'is_free_preview' => true,
            'status' => 'published',
            'duration_seconds' => 600,
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'message' => 'Lesson created successfully.',
            ]);

        $this->assertDatabaseHas('lessons', [
            'course_id' => $this->course->id,
            'title' => 'Lesson 1: React Fundamentals',
            'order' => 1,
            'availability' => 'free',
        ]);
    }

    public function test_teacher_can_reorder_lessons(): void
    {
        $this->actingAs($this->teacher);

        $lesson1 = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'First Lesson',
            'order' => 1,
            'status' => 'published',
        ]);

        $lesson2 = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Second Lesson',
            'order' => 2,
            'status' => 'published',
        ]);

        // Reorder so lesson 2 comes first
        $response = $this->putJson("/api/courses/{$this->course->slug}/lessons/reorder", [
            'lesson_ids' => [$lesson2->id, $lesson1->id],
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Lesson order updated successfully.',
            ]);

        $this->assertEquals(1, $lesson2->fresh()->order);
        $this->assertEquals(2, $lesson1->fresh()->order);
    }

    public function test_teacher_can_update_lesson_availability(): void
    {
        $this->actingAs($this->teacher);

        $lesson = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Premium Lesson',
            'order' => 1,
            'availability' => 'locked',
        ]);

        $response = $this->putJson("/api/courses/{$this->course->slug}/lessons/{$lesson->id}/availability", [
            'availability' => 'free',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('free', $lesson->fresh()->availability);
    }

    public function test_teacher_can_upload_and_delete_resource(): void
    {
        $this->actingAs($this->teacher);

        $lesson = Lesson::create([
            'course_id' => $this->course->id,
            'title' => 'Resource Lesson',
            'order' => 1,
        ]);

        $file = UploadedFile::fake()->create('cheat-sheet.pdf', 500, 'application/pdf');

        $uploadRes = $this->postJson("/api/courses/{$this->course->slug}/lessons/{$lesson->id}/resources", [
            'file' => $file,
            'name' => 'Cheat Sheet PDF',
        ]);

        $uploadRes->assertStatus(201)
            ->assertJson([
                'message' => 'Resource uploaded successfully.',
            ]);

        $resourceId = $uploadRes->json('resource.id');
        $this->assertDatabaseHas('lesson_resources', [
            'id' => $resourceId,
            'lesson_id' => $lesson->id,
            'name' => 'Cheat Sheet PDF',
        ]);

        // Delete resource
        $deleteRes = $this->deleteJson("/api/courses/{$this->course->slug}/lessons/{$lesson->id}/resources/{$resourceId}");
        $deleteRes->assertStatus(200)
            ->assertJson([
                'message' => 'Resource deleted successfully.',
            ]);

        $this->assertDatabaseMissing('lesson_resources', [
            'id' => $resourceId,
        ]);
    }
}
