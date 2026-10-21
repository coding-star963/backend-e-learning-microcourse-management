<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class MediaDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function audio(): string
    {
        $samples = str_repeat("\x00\x00", 100);
        return 'RIFF'.pack('V', 36 + strlen($samples)).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 16000, 2, 16)
            .'data'.pack('V', strlen($samples)).$samples;
    }

    public function test_images_are_delivered_without_a_storage_symlink(): void
    {
        Storage::disk('public')->put('course-thumbnails/cover.png', base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aD1sAAAAASUVORK5CYII='));
        $this->get('/api/media/course-thumbnails/cover.png')->assertOk()
            ->assertHeader('Content-Type', 'image/png')->assertHeader('Accept-Ranges', 'bytes');
    }

    public function test_audio_supports_partial_and_suffix_requests_for_seeking(): void
    {
        $audio = $this->audio();
        Storage::disk('public')->put('lessons/audio/recording.wav', $audio);
        $size = strlen($audio);
        $this->get('/api/media/lessons/audio/recording.wav', ['Range' => 'bytes=8-15'])
            ->assertStatus(206)->assertHeader('Content-Length', '8')
            ->assertHeader('Content-Range', 'bytes 8-15/'.$size);
        $this->get('/api/media/lessons/audio/recording.wav', ['Range' => 'bytes=-10'])
            ->assertStatus(206)->assertHeader('Content-Length', '10')
            ->assertHeader('Content-Range', 'bytes '.($size-10).'-'.($size-1).'/'.$size);
    }

    public function test_video_and_attachment_routes_support_ranges_and_head(): void
    {
        foreach (['lessons/videos/clip.mp4', 'lessons/resources/clip.mp4', 'lessons/resources/notes.pdf'] as $path) {
            Storage::disk('public')->put($path, str_repeat('x', 128));
            $this->get('/api/media/'.$path, ['Range' => 'bytes=32-63'])->assertStatus(206)
                ->assertHeader('Content-Range', 'bytes 32-63/128');
            $this->head('/api/media/'.$path)->assertOk()->assertHeader('Content-Length', '128');
        }
    }

    public function test_media_allows_browser_cors_and_range_headers(): void
    {
        Storage::disk('public')->put('lessons/audio/recording.wav', $this->audio());
        $this->get('/api/media/lessons/audio/recording.wav', ['Origin' => 'http://localhost:9999'])
            ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')
            ->assertHeader('Access-Control-Expose-Headers', 'Accept-Ranges, Content-Length, Content-Range, Content-Type');
        $this->options('/api/media/lessons/audio/recording.wav', [
            'Origin' => 'http://localhost:9999', 'Access-Control-Request-Method' => 'GET',
            'Access-Control-Request-Headers' => 'range,ngrok-skip-browser-warning',
        ])->assertSuccessful()->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_missing_files_and_paths_outside_public_uploads_are_refused(): void
    {
        Storage::disk('public')->put('private.txt', 'private');
        $this->get('/api/media/private.txt')->assertNotFound();
        $this->get('/api/media/lessons/audio/missing.wav')->assertNotFound();
        $this->get('/api/media/lessons/audio/../../private.txt')->assertNotFound();
        $this->get('/api/media/lessons/audio/%2e%2e%2f%2e%2e%2fprivate.txt')->assertNotFound();
    }

    public function test_wav_audio_upload_accepts_the_mime_type_reported_by_php(): void
    {
        $admin = User::factory()->create(['role' => 'administrator']);
        $course = Course::create(['teacher_id' => $admin->id, 'title' => 'Audio Course', 'difficulty_level' => 'beginner']);
        $this->actingAs($admin)->postJson('/api/courses/'.$course->slug.'/lessons', [
            'title' => 'Audio Lesson',
            'audio' => UploadedFile::fake()->createWithContent('recording.wav', $this->audio()),
        ])->assertCreated()->assertJsonPath('lesson.audio_url', fn ($url) => str_contains($url, '/api/media/lessons/audio/'));
    }

    public function test_all_media_models_return_phone_accessible_urls(): void
    {
        $this->assertSame(url('/api/media/course-thumbnails/cover.jpg'), (new Course(['thumbnail' => 'course-thumbnails/cover.jpg']))->thumbnail_url);
        $lesson = new Lesson(['video_path' => 'lessons/videos/clip.mp4', 'audio_path' => 'lessons/audio/recording.wav']);
        $this->assertSame(url('/api/media/lessons/videos/clip.mp4'), $lesson->video_url);
        $this->assertSame(url('/api/media/lessons/audio/recording.wav'), $lesson->audio_url);
        $this->assertSame(url('/api/media/lessons/resources/image.png'), (new LessonResource(['file_path' => 'lessons/resources/image.png']))->file_url);
        $this->assertSame(url('/api/media/profile-photos/avatar.jpg'), (new User(['profile_photo' => 'profile-photos/avatar.jpg']))->profile_photo_url);
        $this->assertSame('https://cdn.example.com/clip.mp4', (new Lesson(['video_path' => 'https://cdn.example.com/clip.mp4']))->video_url);
    }
}
