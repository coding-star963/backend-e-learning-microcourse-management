<?php

namespace App\Http\Controllers\Lesson;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonResource;
use App\Http\Resources\LessonResourceResource;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class LessonController extends Controller
{
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        $query = Lesson::with(['resources']);

        if ($request->has('status') && in_array($request->status, ['draft', 'published', 'archived'])) {
            $query->where('status', $request->status);
        }

        $lessons = $query->orderBy('order')->orderBy('created_at', 'desc')->paginate($request->get('per_page', 50));

        return LessonResource::collection($lessons);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:10000',
            'status' => 'sometimes|in:draft,published,archived',
            'availability' => 'sometimes|in:free,locked,scheduled',
            'is_free_preview' => 'sometimes|boolean',
            'video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg|max:102400',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/wav,audio/ogg|max:51200',
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

        $lesson = Lesson::create($validated);

        return response()->json([
            'lesson' => new LessonResource($lesson->load('resources')),
            'message' => 'Lesson created successfully.',
        ], 201);
    }

    public function show(Course $course, Lesson $lesson): LessonResource
    {
        $lesson->load('resources');

        return new LessonResource($lesson);
    }

    public function update(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:10000',
            'status' => 'sometimes|in:draft,published,archived',
            'availability' => 'sometimes|in:free,locked,scheduled',
            'is_free_preview' => 'sometimes|boolean',
            'video' => 'nullable|file|mimetypes:video/mp4,video/webm,video/ogg|max:102400',
            'audio' => 'nullable|file|mimetypes:audio/mpeg,audio/wav,audio/ogg|max:51200',
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

        $lesson->update($validated);

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson updated successfully.',
        ]);
    }

    public function destroy(Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->video_path) {
            Storage::disk('public')->delete($lesson->video_path);
        }
        if ($lesson->audio_path) {
            Storage::disk('public')->delete($lesson->audio_path);
        }
        foreach ($lesson->resources as $resource) {
            Storage::disk('public')->delete($resource->file_path);
        }

        $lesson->delete();

        return response()->json([
            'message' => 'Lesson deleted successfully.',
        ]);
    }

    public function publish(Course $course, Lesson $lesson): JsonResponse
    {
        $lesson->publish();

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson published successfully.',
        ]);
    }

    public function unpublish(Course $course, Lesson $lesson): JsonResponse
    {
        $lesson->unpublish();

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson unpublished successfully.',
        ]);
    }

    public function archive(Course $course, Lesson $lesson): JsonResponse
    {
        $lesson->archive();

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => 'Lesson archived successfully.',
        ]);
    }

    public function updateStatus(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,published,archived',
        ]);

        $status = $validated['status'];
        match ($status) {
            'published' => $lesson->publish(),
            'archived' => $lesson->archive(),
            default => $lesson->unpublish(),
        };

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => "Lesson status updated to {$status}.",
        ]);
    }

    public function updateAvailability(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $validated = $request->validate([
            'availability' => 'required|in:free,locked,scheduled',
        ]);

        $lesson->update(['availability' => $validated['availability']]);

        return response()->json([
            'lesson' => new LessonResource($lesson->fresh()->load('resources')),
            'message' => "Lesson availability updated to {$validated['availability']}.",
        ]);
    }

    public function reorder(Request $request, Course $course): JsonResponse
    {
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
        $validated = $request->validate([
            'file' => 'required|file|max:102400',
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

        return response()->json([
            'resource' => new LessonResourceResource($resource),
            'message' => 'Resource uploaded successfully.',
        ], 201);
    }

    public function deleteResource(Course $course, Lesson $lesson, \App\Models\LessonResource $resource): JsonResponse
    {
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
}
