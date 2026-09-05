<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
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

        return $this->teacherDashboard($user);
    }

    private function adminDashboard(): array
    {
        $totalUsers = User::count();
        $totalStudents = User::where('role', 'student')->count();
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
}
