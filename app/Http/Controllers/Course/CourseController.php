<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CourseResource;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Course::with(['teacher', 'category']);

        if ($request->has('status') && in_array($request->status, ['draft', 'published', 'archived'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $courses = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return CourseResource::collection($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'duration' => 'nullable|string|max:50',
            'thumbnail' => 'nullable|image|max:2048',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('course-thumbnails', 'public');
        }

        $validated['teacher_id'] = $request->user()->id;
        $validated['is_published'] = ($validated['status'] ?? 'draft') === 'published';

        $course = Course::create($validated);

        return response()->json([
            'course' => new CourseResource($course->load(['teacher', 'category'])),
            'message' => 'Course created successfully.',
        ], 201);
    }

    public function show(Course $course): CourseResource
    {
        $course->load(['teacher', 'category']);

        return new CourseResource($course);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty_level' => 'sometimes|in:beginner,intermediate,advanced',
            'duration' => 'nullable|string|max:50',
            'thumbnail' => 'nullable|image|max:2048',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        if ($request->hasFile('thumbnail')) {
            if ($course->thumbnail) {
                Storage::disk('public')->delete($course->thumbnail);
            }
            $validated['thumbnail'] = $request->file('thumbnail')->store('course-thumbnails', 'public');
        }

        if (isset($validated['status'])) {
            $validated['is_published'] = $validated['status'] === 'published';
        }

        $course->update($validated);

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => 'Course updated successfully.',
        ]);
    }

    public function destroy(Course $course): JsonResponse
    {
        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ]);
    }

    public function publish(Course $course): JsonResponse
    {
        $course->publish();

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => 'Course published successfully.',
        ]);
    }

    public function unpublish(Course $course): JsonResponse
    {
        $course->unpublish();

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => 'Course unpublished successfully.',
        ]);
    }

    public function archive(Course $course): JsonResponse
    {
        $course->archive();

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => 'Course archived successfully.',
        ]);
    }

    public function updateStatus(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:draft,published,archived',
        ]);

        $status = $validated['status'];

        match ($status) {
            'published' => $course->publish(),
            'archived' => $course->archive(),
            default => $course->unpublish(),
        };

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => "Course status updated to {$status}.",
        ]);
    }

    public function categories(): AnonymousResourceCollection
    {
        $categories = Category::orderBy('name')->get();

        return CategoryResource::collection($categories);
    }
}
