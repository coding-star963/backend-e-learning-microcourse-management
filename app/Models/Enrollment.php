<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    const STATUS_ACTIVE = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_SUSPENDED = 'suspended';

    protected $fillable = [
        'user_id',
        'course_id',
        'status',
        'progress',
        'enrolled_at',
        'completed_at',
        'last_accessed_at',
    ];

    protected $casts = [
        'progress' => 'decimal:2',
        'enrolled_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_accessed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Administrators see every enrollment; learners see only their own.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($user->isAdministrator() || (int) $this->user_id === (int) $user->id) {
            return true;
        }

        return false;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isSuspended(): bool
    {
        return $this->status === self::STATUS_SUSPENDED;
    }

    public function complete(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }

    public function cancel(): void
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    public function suspend(): void
    {
        $this->update(['status' => self::STATUS_SUSPENDED]);
    }

    public function reactivate(): void
    {
        $this->update(['status' => self::STATUS_ACTIVE]);
    }

    /**
     * Recompute progress from the course's published lessons, and move the
     * status between active and completed to match. $touch also records the
     * visit (false for background refreshes, e.g. an administrator publishing a lesson).
     */
    public function recalculateProgress(bool $touch = true): void
    {
        $lessonIds = $this->course->publishedLessons()->pluck('id');
        $total = $lessonIds->count();
        $completed = $total === 0 ? 0 : LessonProgress::where('user_id', $this->user_id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('completed', true)
            ->count();
        $progress = $total > 0 ? round(($completed / $total) * 100, 2) : 0;

        $attributes = ['progress' => $progress];
        if ($touch) {
            $attributes['last_accessed_at'] = now();
        }

        if ($total > 0 && $progress >= 100 && $this->status === self::STATUS_ACTIVE) {
            $attributes['status'] = self::STATUS_COMPLETED;
            $attributes['completed_at'] = now();
        } elseif ($total > 0 && $progress < 100 && $this->status === self::STATUS_COMPLETED) {
            // A lesson was added or marked incomplete, so the course is no longer finished.
            $attributes['status'] = self::STATUS_ACTIVE;
            $attributes['completed_at'] = null;
        }

        $this->update($attributes);
    }

    public function updateProgress(float $progress): void
    {
        $this->update([
            'progress' => min(100, max(0, $progress)),
            'last_accessed_at' => now(),
        ]);
    }
}
