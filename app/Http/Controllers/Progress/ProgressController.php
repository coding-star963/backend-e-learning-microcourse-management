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
        $enrollments = Enrollment::with(['course.teacher', 'course.category', 'course.lessons'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->get();

        $coursesProgress = $enrollments->map(function ($enrollment) use ($user) {
            $course = $enrollment->course;
            $totalLessons = $course->lessons->count();
            $completedLessons = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $course->lessons->pluck('id'))
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
        $enrollments = Enrollment::with(['user'])
            ->where('course_id', $course->id)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at', 'desc')
            ->get();

        $totalLessons = Lesson::where('course_id', $course->id)->count();

        $studentsProgress = $enrollments->map(function ($enrollment) use ($course, $totalLessons) {
            $completedLessons = LessonProgress::where('user_id', $enrollment->user_id)
                ->whereIn('lesson_id', $course->lessons->pluck('id'))
                ->where('completed', true)
                ->count();

            $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;

            return [
                'enrollment_id' => $enrollment->id,
                'student' => [
                    'id' => $enrollment->user->id,
                    'name' => $enrollment->user->name,
                    'email' => $enrollment->user->email,
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

    public function enrollmentProgress(Enrollment $enrollment): array
    {
        $enrollment->load(['user', 'course.teacher', 'course.category']);
        $course = $enrollment->course;
        $lessons = $course->lessons;

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
        $query = LessonProgress::with(['lesson.course', 'enrollment'])
            ->where('user_id', $user->id)
            ->where('completed', true);

        if ($request->has('course_id')) {
            $query->whereHas('lesson', function ($q) use ($request) {
                $q->where('course_id', $request->course_id);
            });
        }

        $history = $query->orderBy('completed_at', 'desc')
            ->paginate($request->get('per_page', 15));

        return LessonProgressResource::collection($history);
    }

    public function courseProgressSummary(Course $course): array
    {
        $lessons = Lesson::where('course_id', $course->id)->orderBy('order')->get();
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
        $validated = $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'completed' => 'required|boolean',
            'watch_duration_seconds' => 'sometimes|integer|min:0',
        ]);

        $enrollment = Enrollment::findOrFail($validated['enrollment_id']);

        if ($enrollment->user_id !== $request->user()->id && !$request->user()->isAdministrator()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $progress = LessonProgress::firstOrCreate([
            'user_id' => $enrollment->user_id,
            'lesson_id' => $lesson->id,
            'enrollment_id' => $enrollment->id,
        ]);

        if ($validated['completed']) {
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
            'message' => $validated['completed'] ? 'Lesson marked as completed.' : 'Lesson marked as incomplete.',
        ]);
    }

    private function recalculateEnrollmentProgress(Enrollment $enrollment): void
    {
        $course = $enrollment->course;
        $totalLessons = Lesson::where('course_id', $course->id)->count();

        if ($totalLessons === 0) {
            $enrollment->updateProgress(0);
            return;
        }

        $completedLessons = LessonProgress::where('user_id', $enrollment->user_id)
            ->whereIn('lesson_id', $course->lessons->pluck('id'))
            ->where('completed', true)
            ->count();

        $progress = round(($completedLessons / $totalLessons) * 100, 2);

        $enrollment->update([
            'progress' => $progress,
            'last_accessed_at' => now(),
        ]);

        if ($progress >= 100 && $enrollment->status === Enrollment::STATUS_ACTIVE) {
            $enrollment->complete();
        }
    }
}
