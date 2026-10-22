<?php

namespace App\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * A personal notice for one student (stored in the notifications table and
 * read by the mobile app): an administrator changed their
 * enrollment or their account.
 */
class StudentNotice extends Notification
{
    public function __construct(
        public string $action,
        public string $title,
        public string $content,
        public ?Course $course = null,
        public ?User $actor = null,
        public ?int $enrollmentId = null,
        public ?string $reason = null,
    ) {}

    /**
     * The notice for an enrollment action (enrolled, completed, suspended,
     * reactivated, cancelled or deleted).
     */
    public static function forEnrollment(string $action, Enrollment $enrollment, ?User $actor): self
    {
        $course = $enrollment->course;
        $title = $course->title;

        [$heading, $content] = match ($action) {
            'enrolled' => ['You were enrolled', "You were enrolled in \"{$title}\". It is now in My Courses."],
            'completed' => ['Course marked as completed', "Your enrollment in \"{$title}\" was marked as completed."],
            'suspended' => ['Enrollment suspended', "Your enrollment in \"{$title}\" was suspended. You cannot open its lessons or record progress until it is reactivated. Please contact an administrator."],
            'reactivated' => ['Enrollment reactivated', "Your enrollment in \"{$title}\" is active again. You can continue learning."],
            'cancelled' => ['Enrollment cancelled', "Your enrollment in \"{$title}\" was cancelled. You can no longer open its lessons or record progress. Please contact an administrator if this is a mistake."],
            'deleted' => ['Enrollment removed', "Your enrollment record for \"{$title}\" was deleted, together with its progress. You can enroll again from the course catalog."],
            default => ['Enrollment updated', "Your enrollment in \"{$title}\" was updated."],
        };

        // A deleted enrollment no longer exists, so do not link to it.
        return new self($action, $heading, $content, $course, $actor, $action === 'deleted' ? null : $enrollment->id);
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'action' => $this->action,
            'title' => $this->title,
            'content' => $this->content,
            'course_id' => $this->course?->id,
            'course_title' => $this->course?->title,
            'enrollment_id' => $this->enrollmentId,
            'actor_name' => $this->actor?->name,
            'actor_id' => $this->actor?->id,
            'reason' => $this->reason,
        ];
    }
}
