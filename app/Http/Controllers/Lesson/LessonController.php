<?php

namespace App\Http\Controllers\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Http\Resources\LessonResourceResource;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $user = $request->user();
        abort_unless($course->isVisibleTo($user), 403, 'You do not have access to this course.');

        $query = Lesson::where('course_id', $course->id)->with(['resources', 'quiz']);

        // Students only see published lessons.
        if (! $course->isManageableBy($user)) {
            $query->where('status', Lesson::STATUS_PUBLISHED);
        } elseif ($request->has('status') && in_array($request->status, ['draft', 'published', 'archived'])) {
            $query->where('status', $request->status);
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        $lessons = $query->orderBy('order')->orderBy('created_at', 'desc')->paginate($perPage > 0 ? $perPage : 50);

        if ($user->isStudent()) {
            $this->attachCompletion($lessons->getCollection(), $user->id);
            $this->lockUnavailableMedia($lessons->getCollection(), $course, $user->id);
        }

        return LessonResource::collection($lessons);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'status' => 'sometimes|in:draft,published,archived',
            'availability' => 'sometimes|in:free,locked,scheduled',
            'available_at' => 'nullable|date|required_if:availability,scheduled',
            'is_free_preview' => 'sometimes|boolean',
            'video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg|max:102400',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,audio/ogg|max:51200',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        $validated['course_id'] = $course->id;
        $validated['order'] = Lesson::getNextOrder($course->id);

        if ($request->hasFile('video')) {
            $file = $request->file('video');
            $validated['video_path'] = $file->store('lessons/videos', 'public');
            $validated['video_type'] = $file->getMimeType();
            $validated['video_size'] = $file->getSize();
        }

        if ($request->hasFile('audio')) {
            $file = $request->file('audio');
            $validated['audio_path'] = $file->store('lessons/audio', 'public');
            $validated['audio_type'] = $file->getMimeType();
            $validated['audio_size'] = $file->getSize();
        }

        $validated = $this->normalizeSchedule($validated);
        $lesson = Lesson::create($validated);

        if ($lesson->isPublished()) {
            $this->announceNewLesson($request, $course, $lesson);
            $course->refreshEnrollmentProgress();
        }

        return response()->json([
            'lesson' => new LessonResource($lesson->load('resources')),
            'message' => 'Lesson created successfully.',
        ], 201);
    }

    public function show(Request $request, Course $course, Lesson $lesson): LessonResource
    {
        $user = $request->user();
        abort_unless($course->isVisibleTo($user), 403, 'You do not have access to this course.');

        if (! $course->isManageableBy($user)) {
            abort_unless($lesson->isPublished(), 404, 'Lesson not found.');
        }

        $lesson->load('resources', 'quiz');

        if ($user->isStudent()) {
            $this->attachCompletion(collect([$lesson]), $user->id);
            $this->lockUnavailableMedia(collect([$lesson]), $course, $user->id);
        }

        return new LessonResource($lesson);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:10000',
            'status' => 'sometimes|in:draft,published,archived',
            'availability' => 'sometimes|in:free,locked,scheduled',
            'available_at' => 'nullable|date|required_if:availability,scheduled',
            'is_free_preview' => 'sometimes|boolean',
            'video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg|max:102400',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/wav,audio/x-wav,audio/wave,audio/vnd.wave,audio/ogg|max:51200',
            'duration_seconds' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('video')) {
            if ($lesson->video_path) {
                Storage::disk('public')->delete($lesson->video_path);
            }
            $file = $request->file('video');
            $validated['video_path'] = $file->store('lessons/videos', 'public');
            $validated['video_type'] = $file->getMimeType();
            $validated['video_size'] = $file->getSize();
        }

        if ($request->hasFile('audio')) {
            if ($lesson->audio_path) {
                Storage::disk('public')->delete($lesson->audio_path);
            }
            $file = $request->file('audio');
            $validated['audio_path'] = $file->store('lessons/audio', 'public');
            $validated['audio_type'] = $file->getMimeType();
            $validated['audio_size'] = $file->getSize();
        }

        $wasPublished = $lesson->isPublished();
        $lesson->update($this->normalizeSchedule($validated));

        if (! $wasPublished && $lesson->isPublished()) {
            $this->announceNewLesson($request, $course, $lesson);
        }
        if ($wasPublished !== $lesson->isPublished()) {
            $course->refreshEnrollmentProgress();
        }

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson updated successfully.',
        ]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        if ($lesson->video_path) {
            Storage::disk('public')->delete($lesson->video_path);
        }
        if ($lesson->audio_path) {
            Storage::disk('public')->delete($lesson->audio_path);
        }
        foreach ($lesson->resources as $resource) {
            Storage::disk('public')->delete($resource->file_path);
        }

        $wasPublished = $lesson->isPublished();
        $lesson->delete();

        if ($wasPublished) {
            $course->refreshEnrollmentProgress();
        }

        return response()->json([
            'message' => 'Lesson deleted successfully.',
        ]);
    }

    public function publish(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $wasPublished = $lesson->isPublished();
        $lesson->publish();

        if (! $wasPublished) {
            $this->announceNewLesson($request, $course, $lesson);
            $course->refreshEnrollmentProgress();
        }

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson published successfully.',
        ]);
    }

    public function unpublish(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $wasPublished = $lesson->isPublished();
        $lesson->unpublish();

        if ($wasPublished) {
            $course->refreshEnrollmentProgress();
        }

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson unpublished successfully.',
        ]);
    }

    public function archive(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $wasPublished = $lesson->isPublished();
        $lesson->archive();

        if ($wasPublished) {
            $course->refreshEnrollmentProgress();
        }

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson archived successfully.',
        ]);
    }

    public function updateStatus(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $validated = $request->validate([
            'status' => 'required|in:draft,published,archived',
        ]);

        $status = $validated['status'];
        $wasPublished = $lesson->isPublished();
        match ($status) {
            'published' => $lesson->publish(),
            'archived' => $lesson->archive(),
            default => $lesson->unpublish(),
        };

        if (! $wasPublished && $lesson->isPublished()) {
            $this->announceNewLesson($request, $course, $lesson);
        }
        if ($wasPublished !== $lesson->isPublished()) {
            $course->refreshEnrollmentProgress();
        }

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => "Lesson status updated to {$status}.",
        ]);
    }

    public function updateAvailability(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $validated = $request->validate([
            'availability' => 'required|in:free,locked,scheduled',
            'available_at' => 'nullable|date|required_if:availability,scheduled',
        ]);

        $lesson->update($this->normalizeSchedule([
            'availability' => $validated['availability'],
            'available_at' => $validated['available_at'] ?? null,
        ]));

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => "Lesson availability updated to {$validated['availability']}.",
        ]);
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $validated = $request->validate([
            'lesson_ids' => 'required|array',
            'lesson_ids.*' => 'exists:lessons,id',
        ]);

        foreach ($validated['lesson_ids'] as $index => $lessonId) {
            Lesson::where('id', $lessonId)
                ->where('course_id', $course->id)
                ->update(['order' => $index + 1]);
        }

        $lessons = Lesson::where('course_id', $course->id)
            ->with('resources')
            ->orderBy('order')
            ->get();

        return response()->json([
            'lessons' => LessonResource::collection($lessons),
            'message' => 'Lesson order updated successfully.',
        ]);
    }

    public function addResource(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        // Only learning materials are allowed into public storage (no scripts/executables).
        $validated = $request->validate([
            'file' => 'required|file|max:102400|mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,csv,jpg,jpeg,png,gif,webp,mp4,webm,mp3,wav,ogg',
            'name' => 'nullable|string|max:255',
        ]);

        $file = $request->file('file');
        $nextOrder = ($lesson->resources()->max('order') ?? 0) + 1;

        $resource = $lesson->resources()->create([
            'name' => $validated['name'] ?? $file->getClientOriginalName(),
            'file_path' => $file->store('lessons/resources', 'public'),
            'file_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'order' => $nextOrder,
        ]);

        if ($lesson->isPublished()) {
            Announcement::postCourseUpdate(
                $course,
                $request->user(),
                "New learning material: {$resource->name}",
                "A new resource was added to the lesson \"{$lesson->title}\" in {$course->title}."
            );
        }

        return response()->json([
            'resource' => new LessonResourceResource($resource),
            'message' => 'Resource uploaded successfully.',
        ], 201);
    }

    public function deleteResource(Request $request, Course $course, Lesson $lesson, \App\Models\LessonResource $resource): JsonResponse
    {
        $this->authorizeManage($request, $course);

        if ($resource->lesson_id !== $lesson->id) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        Storage::disk('public')->delete($resource->file_path);
        $resource->delete();

        return response()->json([
            'message' => 'Resource deleted successfully.',
        ]);
    }

    public function reorderResources(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $validated = $request->validate([
            'resource_ids' => 'required|array',
            'resource_ids.*' => 'exists:lesson_resources,id',
        ]);

        foreach ($validated['resource_ids'] as $index => $resourceId) {
            \App\Models\LessonResource::where('id', $resourceId)
                ->where('lesson_id', $lesson->id)
                ->update(['order' => $index + 1]);
        }

        $resources = $lesson->resources()->orderBy('order')->get();

        return response()->json([
            'resources' => LessonResourceResource::collection($resources),
            'message' => 'Resource order updated successfully.',
        ]);
    }

    private function authorizeManage(Request $request, Course $course): void
    {
        abort_unless($course->isManageableBy($request->user()), 403, 'You can only manage lessons in your own courses.');
    }

    /**
     * Flag each lesson with whether this student has completed it.
     */
    private function attachCompletion($lessons, int $userId): void
    {
        $completedIds = LessonProgress::where('user_id', $userId)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->where('completed', true)
            ->pluck('lesson_id')
            ->all();

        foreach ($lessons as $lesson) {
            $lesson->setAttribute('is_completed', in_array($lesson->id, $completedIds));
        }
    }

    /**
     * Students only get a lesson's video, audio and files when they can open it:
     * the lesson is available (not locked or scheduled for later), and they are
     * actively enrolled or the lesson is a free preview.
     */
    private function lockUnavailableMedia($lessons, Course $course, int $userId): void
    {
        $enrolled = $course->enrollments()
            ->where('user_id', $userId)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])
            ->exists();

        foreach ($lessons as $lesson) {
            if (! $lesson->isAvailable() || (! $enrolled && ! $lesson->is_free_preview)) {
                $lesson->setAttribute('media_locked', true);
            }
        }
    }

    /**
     * 6.2 Course updates: tell enrolled students that a new lesson is out.
     */
    private function announceNewLesson(Request $request, Course $course, Lesson $lesson): void
    {
        Announcement::postCourseUpdate(
            $course,
            $request->user(),
            "New lesson: {$lesson->title}",
            "A new lesson is now available in {$course->title}."
        );
    }

    /**
     * Only scheduled lessons keep an opening date.
     */
    private function normalizeSchedule(array $data): array
    {
        if (isset($data['availability']) && $data['availability'] !== Lesson::AVAILABILITY_SCHEDULED) {
            $data['available_at'] = null;
        }

        return $data;
    }
}
