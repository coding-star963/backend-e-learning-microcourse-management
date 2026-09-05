<?php

namespace App\Http\Controllers\Announcement;

use App\Http\Controllers\Controller;
use App\Http\Resources\AnnouncementResource;
use App\Models\Announcement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AnnouncementController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::with(['user', 'course']);

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

    public function show(Announcement $announcement): AnnouncementResource
    {
        $announcement->load(['user', 'course']);

        return new AnnouncementResource($announcement);
    }

    public function update(Request $request, Announcement $announcement): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'content' => 'sometimes|string|max:5000',
            'type' => 'sometimes|in:general,course_update,important',
            'course_id' => 'nullable|exists:courses,id',
            'is_published' => 'sometimes|boolean',
        ]);

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

    public function destroy(Announcement $announcement): JsonResponse
    {
        $announcement->delete();

        return response()->json([
            'message' => 'Announcement deleted successfully.',
        ]);
    }

    public function publish(Announcement $announcement): JsonResponse
    {
        $announcement->publish();

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->fresh()->load(['user', 'course'])),
            'message' => 'Announcement published successfully.',
        ]);
    }

    public function unpublish(Announcement $announcement): JsonResponse
    {
        $announcement->unpublish();

        return response()->json([
            'announcement' => new AnnouncementResource($announcement->fresh()->load(['user', 'course'])),
            'message' => 'Announcement unpublished successfully.',
        ]);
    }

    public function notificationHistory(Request $request): AnonymousResourceCollection
    {
        $query = Announcement::with(['user', 'course'])
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
}
