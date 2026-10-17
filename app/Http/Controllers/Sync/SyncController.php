<?php

namespace App\Http\Controllers\Sync;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\LessonResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Live sync between the web portal and the mobile app.
 *
 * The apps poll this cheap endpoint every few seconds. `version` changes as
 * soon as any data the signed-in user can see is added, changed or deleted,
 * and only then do the apps reload their screens. Suspended or deactivated
 * accounts never get here: the `active` middleware answers with the reason.
 */
class SyncController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        $parts = $user->isStudent()
            ? $this->studentParts($user)
            : $this->staffParts();

        return response()->json([
            'version' => sha1(json_encode($parts)),
            'account_status' => $user->accountStatus(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /**
     * What a student's screens show: their account, enrollments, notices,
     * announcements, and the published catalog with its lessons and files.
     */
    private function studentParts(User $student): array
    {
        $published = Course::where('status', Course::STATUS_PUBLISHED);
        $lessons = Lesson::whereIn('course_id', (clone $published)->select('id'));

        return [
            'me' => [$student->updated_at?->toIso8601String(), $student->accountStatus()],
            'enrollments' => $this->fingerprint(Enrollment::where('user_id', $student->id)),
            'progress' => $this->fingerprint(LessonProgress::where('user_id', $student->id)),
            'notices' => $this->fingerprint($student->notifications()->getQuery(), 'created_at'),
            'announcements' => $this->fingerprint(Announcement::query()->visibleTo($student)),
            'courses' => $this->fingerprint($published),
            'lessons' => $this->fingerprint($lessons),
            // A scheduled lesson opens by itself when its date passes.
            'opened' => (clone $lessons)->where('availability', Lesson::AVAILABILITY_SCHEDULED)
                ->where('available_at', '<=', now())->count(),
            'resources' => $this->fingerprint(LessonResource::whereIn('lesson_id', (clone $lessons)->select('id'))),
            'categories' => $this->fingerprint(Category::query()),
        ];
    }

    /**
     * Web portal pages: accounts, enrollments and progress change from the
     * mobile app (sign-ups, self-enrollment, completed lessons).
     */
    private function staffParts(): array
    {
        return [
            'users' => $this->fingerprint(User::query()),
            'enrollments' => $this->fingerprint(Enrollment::query()),
            'progress' => $this->fingerprint(LessonProgress::query()),
            'courses' => $this->fingerprint(Course::query()),
            'lessons' => $this->fingerprint(Lesson::query()),
            'announcements' => $this->fingerprint(Announcement::query()),
            'categories' => $this->fingerprint(Category::query()),
        ];
    }

    /**
     * Row count plus newest timestamp: catches inserts, updates and deletes.
     */
    private function fingerprint(Builder $query, string $column = 'updated_at'): array
    {
        // Clone: the same query is reused as a subquery elsewhere.
        $row = (clone $query)->toBase()
            ->selectRaw("count(*) as total, max({$column}) as latest")
            ->first();

        return [(int) $row->total, (string) $row->latest];
    }
}
