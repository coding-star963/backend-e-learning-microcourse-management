<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): array
    {
        $user = $request->user();

        if ($user->isAdministrator()) {
            return $this->adminDashboard();
        }

        if ($user->isStudent()) {
            return $this->studentDashboard($user);
        }

        return $this->teacherDashboard($user);
    }

    private function adminDashboard(): array
    {
        $totalUsers = User::count();
        // Learners are students with at least one enrollment; admins and teachers are never counted.
        $totalStudents = User::where('role', 'student')->whereHas('enrollments')->count();
        $totalTeachers = User::where('role', 'teacher')->count();
        $totalCourses = Course::count();
        $publishedCourses = Course::where('status', 'published')->count();
        $draftCourses = Course::where('status', 'draft')->count();
        $archivedCourses = Course::where('status', 'archived')->count();
        $totalEnrollments = Enrollment::count();
        $activeEnrollments = Enrollment::where('status', 'active')->count();
        $completedEnrollments = Enrollment::where('status', 'completed')->count();
        $averageProgress = Enrollment::where('status', 'active')->avg('progress') ?? 0;
        $totalLessons = Lesson::count();
        $totalAnnouncements = Announcement::count();

        $recentCourses = Course::with(['teacher', 'category'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($course) => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'status' => $course->status,
                'teacher' => $course->teacher->name,
                'category' => $course->category->name ?? 'Uncategorized',
                'created_at' => $course->created_at,
            ]);

        $recentEnrollments = Enrollment::with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($enrollment) => [
                'id' => $enrollment->id,
                'student' => $enrollment->user->name,
                'profile_photo' => $enrollment->user->profile_photo_url,
                'course' => $enrollment->course->title,
                'status' => $enrollment->status,
                'progress' => $enrollment->progress,
                'created_at' => $enrollment->created_at,
            ]);

        $recentAnnouncements = Announcement::with(['user'])
            ->where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type,
                'author' => $a->user->name,
                'published_at' => $a->published_at,
            ]);

        return [
            'role' => 'administrator',
            'summary' => [
                'total_users' => $totalUsers,
                'total_students' => $totalStudents,
                'total_teachers' => $totalTeachers,
                'total_courses' => $totalCourses,
                'published_courses' => $publishedCourses,
                'draft_courses' => $draftCourses,
                'archived_courses' => $archivedCourses,
                'total_enrollments' => $totalEnrollments,
                'active_enrollments' => $activeEnrollments,
                'completed_enrollments' => $completedEnrollments,
                'average_progress' => round($averageProgress, 1),
                'total_lessons' => $totalLessons,
                'total_announcements' => $totalAnnouncements,
            ],
            'recent_courses' => $recentCourses,
            'recent_enrollments' => $recentEnrollments,
            'recent_announcements' => $recentAnnouncements,
        ];
    }

    private function teacherDashboard(User $teacher): array
    {
        $totalCourses = Course::where('teacher_id', $teacher->id)->count();
        $publishedCourses = Course::where('teacher_id', $teacher->id)->where('status', 'published')->count();
        $draftCourses = Course::where('teacher_id', $teacher->id)->where('status', 'draft')->count();
        $archivedCourses = Course::where('teacher_id', $teacher->id)->where('status', 'archived')->count();

        $courseIds = Course::where('teacher_id', $teacher->id)->pluck('id');

        $totalEnrollments = Enrollment::whereIn('course_id', $courseIds)->count();
        $activeEnrollments = Enrollment::whereIn('course_id', $courseIds)->where('status', 'active')->count();
        $completedEnrollments = Enrollment::whereIn('course_id', $courseIds)->where('status', 'completed')->count();
        $averageProgress = Enrollment::whereIn('course_id', $courseIds)->where('status', 'active')->avg('progress') ?? 0;

        $totalLessons = Lesson::whereIn('course_id', $courseIds)->count();
        $totalStudents = Enrollment::whereIn('course_id', $courseIds)->distinct('user_id')->count('user_id');

        $recentCourses = Course::where('teacher_id', $teacher->id)
            ->with(['category'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($course) => [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'status' => $course->status,
                'category' => $course->category->name ?? 'Uncategorized',
                'enrollments_count' => $course->enrollments()->count(),
                'created_at' => $course->created_at,
            ]);

        $recentEnrollments = Enrollment::whereIn('course_id', $courseIds)
            ->with(['user', 'course'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($enrollment) => [
                'id' => $enrollment->id,
                'student' => $enrollment->user->name,
                'profile_photo' => $enrollment->user->profile_photo_url,
                'course' => $enrollment->course->title,
                'status' => $enrollment->status,
                'progress' => $enrollment->progress,
                'created_at' => $enrollment->created_at,
            ]);

        $recentAnnouncements = Announcement::where('user_id', $teacher->id)
            ->with(['course'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'title' => $a->title,
                'type' => $a->type,
                'is_published' => $a->is_published,
                'course' => $a->course->title ?? 'All Courses',
                'created_at' => $a->created_at,
            ]);

        return [
            'role' => 'teacher',
            'summary' => [
                'total_courses' => $totalCourses,
                'published_courses' => $publishedCourses,
                'draft_courses' => $draftCourses,
                'archived_courses' => $archivedCourses,
                'total_enrollments' => $totalEnrollments,
                'active_enrollments' => $activeEnrollments,
                'completed_enrollments' => $completedEnrollments,
                'average_progress' => round($averageProgress, 1),
                'total_lessons' => $totalLessons,
                'total_students' => $totalStudents,
            ],
            'recent_courses' => $recentCourses,
            'recent_enrollments' => $recentEnrollments,
            'recent_announcements' => $recentAnnouncements,
        ];
    }

    /**
     * Mobile student dashboard (7.1-7.4): courses, progress, recent activity
     * and recent announcements.
     */
    private function studentDashboard(User $student): array
    {
        $enrollments = Enrollment::with(['course.category', 'course.publishedLessons'])
            ->where('user_id', $student->id)
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])
            ->get();

        $completedIds = LessonProgress::where('user_id', $student->id)
            ->where('completed', true)
            ->pluck('lesson_id')
            ->all();

        $courses = $enrollments->map(function (Enrollment $enrollment) use ($completedIds) {
            $lessons = $enrollment->course->publishedLessons;
            $completed = $lessons->whereIn('id', $completedIds)->count();
            $next = $lessons->first(fn ($lesson) => ! in_array($lesson->id, $completedIds) && $lesson->isAvailable());

            return [
                'enrollment_id' => $enrollment->id,
                'status' => $enrollment->status,
                'course' => [
                    'id' => $enrollment->course->id,
                    'title' => $enrollment->course->title,
                    'slug' => $enrollment->course->slug,
                    'thumbnail' => $enrollment->course->thumbnail ? asset('storage/' . $enrollment->course->thumbnail) : null,
                    'category' => $enrollment->course->category->name ?? 'Uncategorized',
                ],
                'total_lessons' => $lessons->count(),
                'completed_lessons' => $completed,
                'progress' => $lessons->count() > 0 ? round($completed / $lessons->count() * 100, 1) : 0,
                'next_lesson' => $next ? ['id' => $next->id, 'title' => $next->title, 'order' => $next->order] : null,
                'enrolled_at' => $enrollment->enrolled_at,
                'last_accessed_at' => $enrollment->last_accessed_at,
            ];
        });

        // 5.5 Continue Learning: the most recently used course that is not finished.
        $continue = $courses
            ->where('status', Enrollment::STATUS_ACTIVE)
            ->sortByDesc(fn ($course) => $course['last_accessed_at'] ?? $course['enrolled_at'])
            ->first();

        $recentActivity = LessonProgress::with('lesson.course')
            ->where('user_id', $student->id)
            ->where('completed', true)
            ->orderByDesc('completed_at')
            ->take(5)
            ->get()
            ->map(fn (LessonProgress $progress) => [
                'lesson_id' => $progress->lesson_id,
                'lesson_title' => $progress->lesson->title,
                'course_id' => $progress->lesson->course->id,
                'course_title' => $progress->lesson->course->title,
                'completed_at' => $progress->completed_at,
            ]);

        $recentAnnouncements = Announcement::with(['user', 'course'])
            ->visibleTo($student)
            ->orderByDesc('published_at')
            ->take(5)
            ->get()
            ->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'content' => $a->content,
                'type' => $a->type,
                'course' => $a->course->title ?? 'All Courses',
                'author' => $a->user->name,
                'published_at' => $a->published_at,
            ]);

        return [
            'role' => 'student',
            'summary' => [
                'enrolled_courses' => $courses->count(),
                'in_progress_courses' => $courses->where('status', Enrollment::STATUS_ACTIVE)->count(),
                'completed_courses' => $courses->where('status', Enrollment::STATUS_COMPLETED)->count(),
                'completed_lessons' => $courses->sum('completed_lessons'),
                'average_progress' => $courses->count() > 0 ? round($courses->avg('progress'), 1) : 0,
            ],
            'continue_learning' => $continue,
            'courses' => $courses->values(),
            'recent_activity' => $recentActivity,
            'recent_announcements' => $recentAnnouncements,
        ];
    }
}
