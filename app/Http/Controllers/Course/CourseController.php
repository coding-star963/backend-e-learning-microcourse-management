<?php

namespace App\Http\Controllers\Course;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\CourseResource;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CourseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();
        $query = Course::with(['teacher', 'category', 'publishedLessons:id,course_id,order,status']);

        // Teachers only see their own courses; students only see published ones.
        if ($user->isTeacher()) {
            $query->where('teacher_id', $user->id);
        } elseif ($user->isStudent()) {
            $query->where('status', Course::STATUS_PUBLISHED);
        }

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

        $perPage = min((int) $request->get('per_page', 15), 100);
        $courses = $query->orderBy('created_at', 'desc')->paginate($perPage > 0 ? $perPage : 15);

        return CourseResource::collection($courses);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'learning_objectives' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'duration' => 'nullable|string|max:50',
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)],
            'section' => 'nullable|string|max:100',
            'thumbnail' => 'nullable|image|max:10240',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        if ($request->hasFile('thumbnail')) {
            $validated['thumbnail'] = $request->file('thumbnail')->store('course-thumbnails', 'public');
        }

        // Administrators assign a teacher; teachers always own the courses they create.
        if (! $request->user()->isAdministrator() || empty($validated['teacher_id'])) {
            $validated['teacher_id'] = $request->user()->id;
        }
        $validated['is_published'] = ($validated['status'] ?? 'draft') === 'published';

        $course = Course::create($validated);

        return response()->json([
            'course' => new CourseResource($course->load(['teacher', 'category'])),
            'message' => 'Course created successfully.',
        ], 201);
    }

    public function show(Request $request, Course $course): CourseResource
    {
        abort_unless($course->isVisibleTo($request->user()), 403, 'You do not have access to this course.');

        $course->load(['teacher', 'category', 'publishedLessons:id,course_id,order,status']);

        return new CourseResource($course);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:5000',
            'learning_objectives' => 'nullable|string|max:5000',
            'category_id' => 'nullable|exists:categories,id',
            'difficulty_level' => 'sometimes|in:beginner,intermediate,advanced',
            'duration' => 'nullable|string|max:50',
            'teacher_id' => ['nullable', Rule::exists('users', 'id')->where('role', User::ROLE_TEACHER)],
            'section' => 'nullable|string|max:100',
            'thumbnail' => 'nullable|image|max:10240',
            'status' => 'sometimes|in:draft,published,archived',
        ]);

        if (! $request->user()->isAdministrator() || empty($validated['teacher_id'])) {
            unset($validated['teacher_id']);
        }

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

    public function destroy(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

        if ($course->thumbnail) {
            Storage::disk('public')->delete($course->thumbnail);
        }

        $course->delete();

        return response()->json([
            'message' => 'Course deleted successfully.',
        ]);
    }

    public function publish(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $course->publish();

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => 'Course published successfully.',
        ]);
    }

    public function unpublish(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $course->unpublish();

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => 'Course unpublished successfully.',
        ]);
    }

    public function archive(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

        $course->archive();

        return response()->json([
            'course' => new CourseResource($course->fresh()->load(['teacher', 'category'])),
            'message' => 'Course archived successfully.',
        ]);
    }

    public function updateStatus(Request $request, Course $course): JsonResponse
    {
        $this->authorizeManage($request, $course);

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

    private function authorizeManage(Request $request, Course $course): void
    {
        abort_unless($course->isManageableBy($request->user()), 403, 'You can only manage your own courses.');
    }
}
