<?php

namespace App\Http\Controllers\Announcement;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::with(['user', 'course']);
        $query->visibleTo($request->user());

        if ($request->has('type') && in_array($request->type, ['general', 'course_update', 'important'])) {
            $query->where('type', $request->type);
        }

        if ($request->has('is_published')) {
            $query->where('is_published', $request->boolean('is_published'));
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $announcements = $query->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return AnnouncementResource::collection($announcements);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string|max:5000',
            'type' => 'sometimes|in:general,course_update,important',
            'course_id' => 'nullable|exists:courses,id',
            'is_published' => 'sometimes|boolean',
        ]);

        $this->authorizeCourse($request->user(), $validated['course_id'] ?? null);

        $validated['user_id'] = $request->user()->id;

        if (isset($validated['is_published']) && $validated['is_published']) {
            $validated['published_at'] = now();
        }

        $announcement = Announcement::create($validated);

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->load(['user', 'course'])),
            'message' => 'Announcement created successfully.',
        ], 201);
    }

    public function show(Request $request, Announcement $announcement): AnnouncementResource
    {
        $query = Announcement::whereKey($announcement->id);
        $query->visibleTo($request->user());
        abort_unless($query->exists(), 404, 'Announcement not found.');

        $announcement->load(['user', 'course']);

        return new AnnouncementResource($announcement);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->isManageableBy($request->user()), 403, 'You can only manage your own announcements.');

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|max:5000',
            'type' => 'sometimes|in:general,course_update,important',
            'course_id' => 'nullable|exists:courses,id',
            'is_published' => 'sometimes|boolean',
        ]);

        if (array_key_exists('course_id', $validated)) {
            $this->authorizeCourse($request->user(), $validated['course_id']);
        }

        if (isset($validated['is_published'])) {
            if ($validated['is_published'] && !$announcement->is_published) {
                $validated['published_at'] = now();
            } elseif (!$validated['is_published']) {
                $validated['published_at'] = null;
            }
        }

        $announcement->update($validated);

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->fresh()->load(['user', 'course'])),
            'message' => 'Announcement updated successfully.',
        ]);
    }

    public function destroy(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->isManageableBy($request->user()), 403, 'You can only manage your own announcements.');

        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    public function publish(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->isManageableBy($request->user()), 403, 'You can only manage your own announcements.');

        $announcement->publish();

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->fresh()->load(['user', 'course'])),
            'message' => 'Announcement published successfully.',
        ]);
    }

    public function unpublish(Request $request, Announcement $announcement): JsonResponse
    {
        abort_unless($announcement->isManageableBy($request->user()), 403, 'You can only manage your own announcements.');

        $announcement->unpublish();

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->fresh()->load(['user', 'course'])),
            'message' => 'Announcement unpublished successfully.',
        ]);
    }

    public function notificationHistory(Request $request): AnonymousResourceCollection
    {
        // Teachers only see general notices and those for their own courses.
        $query = Announcement::with(['user', 'course'])
            ->visibleTo($request->user())
            ->where('is_published', true);

        if ($request->has('type') && in_array($request->type, ['general', 'course_update', 'important'])) {
            $query->where('type', $request->type);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $announcements = $query->orderBy('published_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return AnnouncementResource::collection($announcements);
    }

    /**
     * Teachers may only target courses they own (null = all courses).
     */
    private function authorizeCourse(User $user, $courseId): void
    {
        if ($courseId === null || $user->isAdministrator()) {
            return;
        }

        abort_unless(
            Course::whereKey($courseId)->where('teacher_id', $user->id)->exists(),
            403,
            'You can only post announcements to your own courses.'
        );
    }
}
