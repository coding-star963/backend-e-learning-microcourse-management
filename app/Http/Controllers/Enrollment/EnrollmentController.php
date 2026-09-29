<?php

namespace App\Http\Controllers\Enrollment;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\UserResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EnrollmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Enrollment::with(['user', 'course.teacher', 'course.category']);

        if ($request->has('status') && in_array($request->status, ['active', 'completed', 'cancelled', 'suspended'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('user', function ($uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })->orWhereHas('course', function ($cq) use ($search) {
                    $cq->where('title', 'like', "%{$search}%");
                });
            });
        }

        $enrollments = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return EnrollmentResource::collection($enrollments);
    }

    public function store(Request $request): JsonResponse
    {
        // Either enroll an existing student (user_id) or register a new student and enroll them.
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'user_id' => ['required_without:name', 'nullable', Rule::exists('users', 'id')->where('role', User::ROLE_STUDENT)],
            'name' => 'required_without:user_id|nullable|string|max:255',
            'email' => 'required_without:user_id|nullable|email|max:255|unique:users,email',
            'password' => 'required_without:user_id|nullable|string|min:8',
        ], [
            'user_id.exists' => 'The selected account is not a student.',
        ]);

        if (! empty($validated['user_id'])) {
            $exists = Enrollment::where('user_id', $validated['user_id'])
                ->where('course_id', $validated['course_id'])
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'This student is already enrolled in this course.',
                ], 422);
            }
        }

        $enrollment = DB::transaction(function () use ($validated) {
            $userId = $validated['user_id'] ?? null;

            if (! $userId) {
                $userId = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => $validated['password'],
                    'role' => User::ROLE_STUDENT,
                    'is_active' => true,
                ])->id;
            }

            return Enrollment::create([
                'user_id' => $userId,
                'course_id' => $validated['course_id'],
                'status' => 'active',
                'progress' => 0,
                'enrolled_at' => now(),
            ]);
        });

        return response()->json([
            'enrollment' => new EnrollmentResource($enrollment->load(['user', 'course.teacher', 'course.category'])),
            'message' => 'Student enrolled successfully.',
        ], 201);
    }

    /**
     * Active student accounts that can be enrolled, with their enrollment counts.
     */
    public function students(Request $request): AnonymousResourceCollection
    {
        $query = User::where('role', User::ROLE_STUDENT)
            ->where('is_active', true)
            ->withCount('enrollments');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return UserResource::collection($query->orderBy('name')->get());
    }

    public function show(Enrollment $enrollment): EnrollmentResource
    {
        $enrollment->load(['user', 'course.teacher', 'course.category', 'course.lessons']);

        return new EnrollmentResource($enrollment);
    }

    public function update(Request $request, Enrollment $enrollment): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|in:active,completed,cancelled,suspended',
            'progress' => 'sometimes|numeric|min:0|max:100',
        ]);

        if (isset($validated['status'])) {
            match ($validated['status']) {
                'completed' => $enrollment->complete(),
                'cancelled' => $enrollment->cancel(),
                'suspended' => $enrollment->suspend(),
                default => $enrollment->reactivate(),
            };
            unset($validated['status']);
        }

        if (!empty($validated)) {
            $enrollment->update($validated);
        }

        return response()->json([
            'enrollment' => new EnrollmentResource($enrollment->fresh()->load(['user', 'course.teacher', 'course.category'])),
            'message' => 'Enrollment updated successfully.',
        ]);
    }

    public function destroy(Enrollment $enrollment): JsonResponse
    {
        $enrollment->delete();

        return response()->json([
            'message' => 'Enrollment record deleted successfully.',
        ]);
    }

    public function stats(): array
    {
        $total = Enrollment::count();
        $active = Enrollment::where('status', 'active')->count();
        $completed = Enrollment::where('status', 'completed')->count();
        $cancelled = Enrollment::where('status', 'cancelled')->count();
        $suspended = Enrollment::where('status', 'suspended')->count();
        $averageProgress = Enrollment::where('status', 'active')->avg('progress') ?? 0;

        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'suspended' => $suspended,
            'average_progress' => round($averageProgress, 1),
        ];
    }

    public function courseStats(Course $course): array
    {
        $total = Enrollment::where('course_id', $course->id)->count();
        $active = Enrollment::where('course_id', $course->id)->where('status', 'active')->count();
        $completed = Enrollment::where('course_id', $course->id)->where('status', 'completed')->count();
        $averageProgress = Enrollment::where('course_id', $course->id)->where('status', 'active')->avg('progress') ?? 0;

        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
            'average_progress' => round($averageProgress, 1),
        ];
    }

    public function courseEnrollments(Request $request, Course $course): AnonymousResourceCollection
    {
        $query = Enrollment::with(['user'])
            ->where('course_id', $course->id);

        if ($request->has('status') && in_array($request->status, ['active', 'completed', 'cancelled', 'suspended'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('user', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $enrollments = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return EnrollmentResource::collection($enrollments);
    }

    public function studentEnrollments(Request $request, User $user): AnonymousResourceCollection
    {
        $query = Enrollment::with(['course.teacher', 'course.category'])
            ->where('user_id', $user->id);

        if ($request->has('status') && in_array($request->status, ['active', 'completed', 'cancelled', 'suspended'])) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->whereHas('course', function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%");
            });
        }

        $enrollments = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return EnrollmentResource::collection($enrollments);
    }
}
