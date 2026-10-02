<?php

namespace App\Http\Controllers\Progress;

use App\Http\Controllers\Controller;
use App\Http\Resources\LessonProgressResource;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProgressController extends Controller
{
    public function studentProgress(Request $request, User $user): array
    {
        $courseIds = $this->allowedCourseIds($request->user(), $user);

        $enrollments = Enrollment::with(['course.teacher', 'course.category', 'course.publishedLessons'])
            ->where('user_id', $user->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])
            ->when($courseIds !== null, fn ($query) => $query->whereIn('course_id', $courseIds))
            ->get();

        $coursesProgress = $enrollments->map(function ($enrollment) use ($user) {
            $course = $enrollment->course;
            $totalLessons = $course->publishedLessons->count();
            $completedLessons = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $course->publishedLessons->pluck('id'))
                ->where('completed', true)
                ->count();

            $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;

            return [
                'enrollment_id' => $enrollment->id,
                'course' => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'slug' => $course->slug,
                    'teacher' => $course->teacher->name,
                    'category' => $course->category->name ?? 'Uncategorized',
                ],
                'status' => $enrollment->status,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress' => $progress,
                'enrolled_at' => $enrollment->enrolled_at,
                'last_accessed_at' => $enrollment->last_accessed_at,
            ];
        });

        $totalCourses = $enrollments->count();
        $completedCourses = $enrollments->where('status', 'completed')->count();
        $averageProgress = $totalCourses > 0
            ? round($coursesProgress->avg('progress'), 1)
            : 0;
        return [
            'student' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_photo' => $user->profile_photo_url,
            ],
            'summary' => [
                'total_courses' => $totalCourses,
                'completed_courses' => $completedCourses,
                'average_progress' => $averageProgress,
            ],
            'courses' => $coursesProgress->values(),
        ];
    }

    public function courseProgress(Request $request, Course $course): array
    {
        $this->authorizeCourse($request, $course);

        $enrollments = Enrollment::with(['user'])
            ->where('course_id', $course->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->get();

        $lessonIds = $course->publishedLessons()->pluck('id');
        $totalLessons = $lessonIds->count();

        $studentsProgress = $enrollments->map(function ($enrollment) use ($lessonIds, $totalLessons) {
            $completedLessons = LessonProgress::where('user_id', $enrollment->user_id)
                ->whereIn('lesson_id', $lessonIds)
                ->where('completed', true)
                ->count();

            $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;

            return [
                'enrollment_id' => $enrollment->id,
                'student' => [
                    'id' => $enrollment->user->id,
                    'name' => $enrollment->user->name,
                    'email' => $enrollment->user->email,
                    'profile_photo' => $enrollment->user->profile_photo_url,
                ],
                'status' => $enrollment->status,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress' => $progress,
                'enrolled_at' => $enrollment->enrolled_at,
                'last_accessed_at' => $enrollment->last_accessed_at,
            ];
        });

        $activeCount = $enrollments->where('status', 'active')->count();
        $completedCount = $enrollments->where('status', 'completed')->count();
        $averageProgress = $studentsProgress->count() > 0
            ? round($studentsProgress->avg('progress'), 1)
            : 0;

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'summary' => [
                'total_lessons' => $totalLessons,
                'total_students' => $enrollments->count(),
                'active_students' => $activeCount,
                'completed_students' => $completedCount,
                'average_progress' => $averageProgress,
            ],
            'students' => $studentsProgress->values(),
        ];
    }

    public function enrollmentProgress(Request $request, Enrollment $enrollment): array
    {
        abort_unless($enrollment->isVisibleTo($request->user()), 403, 'You do not have access to this enrollment.');

        $enrollment->load(['user', 'course.teacher', 'course.category', 'course.publishedLessons']);
        $course = $enrollment->course;
        $lessons = $course->publishedLessons;

        $lessonProgress = LessonProgress::where('user_id', $enrollment->user_id)
            ->whereIn('lesson_id', $lessons->pluck('id'))
            ->get()
            ->keyBy('lesson_id');

        $lessonsData = $lessons->map(function ($lesson) use ($lessonProgress, $enrollment) {
            $progress = $lessonProgress->get($lesson->id);
            $completed = $progress?->completed ?? false;

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'order' => $lesson->order,
                'duration_seconds' => $lesson->duration_seconds,
                'duration_formatted' => $lesson->duration_formatted,
                'completed' => $completed,
                'completed_at' => $progress?->completed_at,
                'watch_duration_seconds' => $progress?->watch_duration_seconds ?? 0,
            ];
        });

        $totalLessons = $lessons->count();
        $completedLessons = $lessonsData->where('completed', true)->count();
        $totalDuration = $lessons->sum('duration_seconds');
        $watchedDuration = $lessonProgress->sum('watch_duration_seconds');

        return [
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'progress' => $enrollment->progress,
                'enrolled_at' => $enrollment->enrolled_at,
                'completed_at' => $enrollment->completed_at,
                'last_accessed_at' => $enrollment->last_accessed_at,
            ],
            'student' => [
                'id' => $enrollment->user->id,
                'name' => $enrollment->user->name,
                'email' => $enrollment->user->email,
                'profile_photo' => $enrollment->user->profile_photo_url,
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'teacher' => $course->teacher->name,
                'category' => $course->category->name ?? 'Uncategorized',
            ],
            'summary' => [
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'total_duration_seconds' => $totalDuration,
                'watched_duration_seconds' => $watchedDuration,
                'completion_percentage' => $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0,
            ],
            'lessons' => $lessonsData->values(),
        ];
    }

    public function learningHistory(Request $request, User $user): AnonymousResourceCollection
    {
        $courseIds = $this->allowedCourseIds($request->user(), $user);

        $query = LessonProgress::with(['lesson.course', 'enrollment'])
            ->where('user_id', $user->id)
            ->where('completed', true);

        if ($courseIds !== null) {
            $query->whereHas('lesson', fn ($q) => $q->whereIn('course_id', $courseIds));
        }

        if ($request->has('course_id')) {
            $query->whereHas('lesson', function ($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        $perPage = min((int) $request->get('per_page', 15), 100);
        $history = $query->orderBy('completed_at', 'desc')
            ->paginate($perPage > 0 ? $perPage : 15);

        return LessonProgressResource::collection($history);
    }

    public function courseProgressSummary(Request $request, Course $course): array
    {
        $this->authorizeCourse($request, $course);

        $lessons = $course->publishedLessons()->get();
        $enrollments = Enrollment::where('course_id', $course->id)->where('status', '!=', 'cancelled')->get();

        $lessonsSummary = $lessons->map(function ($lesson) use ($enrollments) {
            $completedCount = LessonProgress::where('lesson_id', $lesson->id)
                ->where('completed', true)
                ->whereIn('enrollment_id', $enrollments->pluck('id'))
                ->count();

            $totalStudents = $enrollments->count();
            $completionRate = $totalStudents > 0 ? round(($completedCount / $totalStudents) * 100, 1) : 0;

            return [
                'id' => $lesson->id,
                'title' => $lesson->title,
                'order' => $lesson->order,
                'duration_seconds' => $lesson->duration_seconds,
                'duration_formatted' => $lesson->duration_formatted,
                'total_students' => $totalStudents,
                'completed_students' => $completedCount,
                'completion_rate' => $completionRate,
            ];
        });

        $totalStudents = $enrollments->count();
        $activeStudents = $enrollments->where('status', 'active')->count();
        $completedStudents = $enrollments->where('status', 'completed')->count();
        $averageProgress = $enrollments->avg('progress') ?? 0;

        return [
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
            ],
            'summary' => [
                'total_lessons' => $lessons->count(),
                'total_students' => $totalStudents,
                'active_students' => $activeStudents,
                'completed_students' => $completedStudents,
                'average_progress' => round($averageProgress, 1),
            ],
            'lessons' => $lessonsSummary->values(),
        ];
    }

    public function toggleLessonProgress(Request $request, Lesson $lesson): JsonResponse
    {
        // enrollment_id is optional: a student's own enrollment in the lesson's course is used by default.
        $validated = $request->validate([
            'enrollment_id' => 'sometimes|exists:enrollments,id',
            'completed' => 'sometimes|boolean',
            'watch_duration_seconds' => 'sometimes|integer|min:0',
        ]);
        $completed = $validated['completed'] ?? true;
        $user = $request->user();

        $enrollment = isset($validated['enrollment_id'])
            ? Enrollment::findOrFail($validated['enrollment_id'])
            : Enrollment::where('user_id', $user->id)->where('course_id', $lesson->course_id)->first();

        if (! $enrollment) {
            return response()->json(['message' => 'You are not enrolled in this course.'], 403);
        }

        if ((int) $enrollment->user_id !== (int) $user->id && ! $user->isAdministrator()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ((int) $lesson->course_id !== (int) $enrollment->course_id) {
            return response()->json(['message' => 'This lesson does not belong to the enrolled course.'], 422);
        }

        if (! in_array($enrollment->status, [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])) {
            return response()->json(['message' => 'This enrollment is not active.'], 422);
        }

        if (! $lesson->isAvailable()) {
            return response()->json(['message' => 'This lesson is not available yet.'], 422);
        }

        $progress = LessonProgress::firstOrCreate([
            'user_id' => $enrollment->user_id,
            'lesson_id' => $lesson->id,
            'enrollment_id' => $enrollment->id,
        ]);

        if ($completed) {
            $progress->markComplete();
        } else {
            $progress->markIncomplete();
        }

        if (isset($validated['watch_duration_seconds'])) {
            $progress->updateWatchDuration($validated['watch_duration_seconds']);
        }

        $this->recalculateEnrollmentProgress($enrollment);

        return response()->json([
            'progress' => new LessonProgressResource($progress->fresh()->load(['lesson', 'enrollment'])),
            'message' => $completed ? 'Lesson marked as completed.' : 'Lesson marked as incomplete.',
        ]);
    }

    private function recalculateEnrollmentProgress(Enrollment $enrollment): void
    {
        $lessonIds = $enrollment->course->publishedLessons()->pluck('id');
        $totalLessons = $lessonIds->count();

        if ($totalLessons === 0) {
            $enrollment->updateProgress(0);
            return;
        }

        $completedLessons = LessonProgress::where('user_id', $enrollment->user_id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('completed', true)
            ->count();

        $progress = round(($completedLessons / $totalLessons) * 100, 2);

        $enrollment->update([
            'progress' => $progress,
            'last_accessed_at' => now(),
        ]);

        if ($progress >= 100 && $enrollment->status === Enrollment::STATUS_ACTIVE) {
            $enrollment->complete();
        } elseif ($progress < 100 && $enrollment->status === Enrollment::STATUS_COMPLETED) {
            // A lesson was marked incomplete again, so the course is no longer finished.
            $enrollment->update([
                'status' => Enrollment::STATUS_ACTIVE,
                'completed_at' => null,
            ]);
        }
    }

    /**
     * Course ids the viewer may see for this student's progress:
     * null = all (administrator or the student themself), or the teacher's own course ids.
     */
    private function allowedCourseIds(User $viewer, User $student): ?array
    {
        if ($viewer->isAdministrator() || (int) $viewer->id === (int) $student->id) {
            return null;
        }

        abort_unless($viewer->isTeacher(), 403, 'You can only view your own progress.');

        return Course::where('teacher_id', $viewer->id)->pluck('id')->all();
    }

    private function authorizeCourse(Request $request, Course $course): void
    {
        abort_unless($course->isManageableBy($request->user()), 403, 'You can only view your own courses.');
    }
}
