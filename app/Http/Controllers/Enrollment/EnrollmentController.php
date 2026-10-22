<?php

namespace App\Http\Controllers\Enrollment;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Http\Resources\UserResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Notifications\StudentNotice;
use App\Rules\GmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
        if ($request->user()->isStudent()) {
            return $this->selfEnroll($request);
        }

        // Either enroll an existing student (user_id) or register a new student and enroll them.
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'user_id' => ['required_without:name', 'nullable', Rule::exists('users', 'id')->where('role', User::ROLE_STUDENT)],
            'name' => 'required_without:user_id|nullable|string|max:255',
            'email' => ['required_without:user_id', 'nullable', 'email', 'max:255', 'unique:users,email', new GmailAddress],
            'password' => 'required_without:user_id|nullable|string|min:8',
        ], [
            'user_id.exists' => 'The selected account is not a student.',
        ]);

        $course = Course::findOrFail($validated['course_id']);
        abort_unless($course->isManageableBy($request->user()), 403, 'Only administrators can enroll learners.');
        if (! $course->isPublished()) {
            return response()->json(['message' => 'This module is not available for enrollment. Choose a published module.'], 422);
        }

        if (! empty($validated['user_id'])) {
            // Suspended students can still be enrolled; deactivated ones are frozen.
            if (User::find($validated['user_id'])->isInactive()) {
                return response()->json(['message' => User::FROZEN_MESSAGE], 422);
            }

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

            // The unique learner/module index also handles simultaneous requests.
            // Reusing a record must never reset its status or learning progress.
            $enrollment = Enrollment::createOrFirst([
                'user_id' => $userId,
                'course_id' => $validated['course_id'],
            ], [
                'status' => 'active',
                'progress' => 0,
                'enrolled_at' => now(),
            ]);
            if (! $enrollment->wasRecentlyCreated) {
                throw ValidationException::withMessages([
                    'course_id' => 'This student is already enrolled in this course.',
                ]);
            }

            return $enrollment;
        });

        $enrollment->load(['user', 'course.teacher', 'course.category']);
        $enrollment->user->notify(StudentNotice::forEnrollment('enrolled', $enrollment, $request->user()));

        return response()->json([
            'enrollment' => new EnrollmentResource($enrollment),
            'message' => 'Student enrolled successfully.',
        ], 201);
    }

    /**
     * Student accounts that are not deactivated (active or suspended), with
     * their enrollment counts. Deactivated students are frozen.
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

    public function show(Request $request, Enrollment $enrollment): EnrollmentResource
    {
        abort_unless($enrollment->isVisibleTo($request->user()), 403, 'You do not have access to this enrollment.');

        $enrollment->load(['user', 'course.teacher', 'course.category', 'course.lessons', 'course.publishedLessons:id,course_id,order,status', 'lessonProgress']);

        return new EnrollmentResource($enrollment);
    }

    public function update(Request $request, Enrollment $enrollment): JsonResponse
    {
        abort_unless($enrollment->isVisibleTo($request->user()), 403, 'You can only manage enrollments in your own courses.');
        abort_if($enrollment->user->isInactive(), 422, User::FROZEN_MESSAGE);

        $validated = $request->validate([
            'status' => 'sometimes|in:active,completed,cancelled,suspended',
            'progress' => 'sometimes|numeric|min:0|max:100',
        ]);

        if (isset($validated['status'])) {
            $previous = $enrollment->status;
            match ($validated['status']) {
                'completed' => $enrollment->complete(),
                'cancelled' => $enrollment->cancel(),
                'suspended' => $enrollment->suspend(),
                default => $enrollment->reactivate(),
            };

            // Tell the student (mobile app) what changed.
            if ($previous !== $enrollment->status) {
                $action = match ($enrollment->status) {
                    Enrollment::STATUS_COMPLETED => 'completed',
                    Enrollment::STATUS_CANCELLED => 'cancelled',
                    Enrollment::STATUS_SUSPENDED => 'suspended',
                    default => 'reactivated',
                };
                $enrollment->user->notify(StudentNotice::forEnrollment($action, $enrollment, $request->user()));
            }
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

    public function destroy(Request $request, Enrollment $enrollment): JsonResponse
    {
        abort_unless($enrollment->isVisibleTo($request->user()), 403, 'You can only manage enrollments in your own courses.');
        abort_if($enrollment->user->isInactive(), 422, User::FROZEN_MESSAGE);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000', 'regex:/\S/u'],
        ]);

        // The enrollment's lesson progress is removed with it (cascade), so the
        // student can enroll again from scratch.
        $notice = StudentNotice::forEnrollment('deleted', $enrollment, $request->user());
        $notice->reason = trim($validated['reason']);
        $notice->content .= " Reason: {$notice->reason}";
        $student = $enrollment->user;
        DB::transaction(function () use ($enrollment, $student, $notice) {
            $enrollment->delete();
            $student->notify($notice);
        });

        return response()->json([
            'message' => 'Enrollment record deleted successfully.',
        ]);
    }

    public function stats(Request $request): array
    {
        $base = Enrollment::query();

        $total = (clone $base)->count();
        $active = (clone $base)->where('status', 'active')->count();
        $completed = (clone $base)->where('status', 'completed')->count();
        $cancelled = (clone $base)->where('status', 'cancelled')->count();
        $suspended = (clone $base)->where('status', 'suspended')->count();
        $averageProgress = (clone $base)->where('status', 'active')->avg('progress') ?? 0;

        return [
            'total' => $total,
            'active' => $active,
            'completed' => $completed,
            'cancelled' => $cancelled,
            'suspended' => $suspended,
            'average_progress' => round($averageProgress, 1),
        ];
    }

    public function courseStats(Request $request, Course $course): array
    {
        abort_unless($course->isManageableBy($request->user()), 403, 'You can only view your own courses.');

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
        abort_unless($course->isManageableBy($request->user()), 403, 'You can only view your own courses.');

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
        $viewer = $request->user();
        abort_unless(
            $viewer->isAdministrator() || (int) $viewer->id === (int) $user->id,
            403,
            'You can only view your own enrollments.'
        );

        $query = Enrollment::with(['course.teacher', 'course.category', 'course.publishedLessons:id,course_id,order,status', 'lessonProgress'])
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

    /**
     * A student enrolls themself in a published course (mobile app).
     */
    private function selfEnroll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'course_id' => 'required|exists:courses,id',
        ]);

        $student = $request->user();
        $course = Course::findOrFail($validated['course_id']);

        if (! $course->isPublished()) {
            return response()->json([
                'message' => 'This course is not open for enrollment.',
            ], 422);
        }

        $existing = Enrollment::where('user_id', $student->id)
            ->where('course_id', $course->id)
            ->first();

        if ($existing) {
            $blocked = in_array($existing->status, [Enrollment::STATUS_CANCELLED, Enrollment::STATUS_SUSPENDED]);

            return response()->json([
                'message' => $blocked
                    ? "Your enrollment in this course was {$existing->status}. Please contact an administrator."
                    : 'You are already enrolled in this course.',
            ], 422);
        }

        $enrollment = Enrollment::createOrFirst([
            'user_id' => $student->id,
            'course_id' => $course->id,
        ], [
            'status' => Enrollment::STATUS_ACTIVE,
            'progress' => 0,
            'enrolled_at' => now(),
        ]);

        if (! $enrollment->wasRecentlyCreated) {
            return response()->json(['message' => 'You are already enrolled in this course.'], 422);
        }

        return response()->json([
            'enrollment' => new EnrollmentResource($enrollment->load(['user', 'course.teacher', 'course.category', 'lessonProgress'])),
            'message' => 'Enrolled successfully.',
        ], 201);
    }

}
