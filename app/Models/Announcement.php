<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasFactory;

    const TYPE_GENERAL = 'general';
    const TYPE_COURSE_UPDATE = 'course_update';
    const TYPE_IMPORTANT = 'important';

    protected $fillable = [
        'user_id',
        'course_id',
        'title',
        'content',
        'type',
        'is_published',
        'published_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Administrators see everything. Learners see published notices for all courses
     * or courses they are enrolled in.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->isAdministrator()) {
            return;
        }

        $courseIds = Enrollment::where('user_id', $user->id)->select('course_id');

        $query->where(function ($q) use ($courseIds) {
            $q->where(function ($published) use ($courseIds) {
                $published->where('is_published', true)
                    ->where(fn ($scope) => $scope->whereNull('course_id')->orWhereIn('course_id', $courseIds));
            });
        });
    }

    /**
     * Publish an automatic "course update" to students of a published course
     * (e.g. a new lesson or new learning material).
     */
    public static function postCourseUpdate(Course $course, User $author, string $title, string $content): void
    {
        if (! $course->isPublished()) {
            return;
        }

        static::create([
            'user_id' => $author->id,
            'course_id' => $course->id,
            'title' => $title,
            'content' => $content,
            'type' => self::TYPE_COURSE_UPDATE,
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    /**
     * Administrators manage every announcement.
     */
    public function isManageableBy(User $user): bool
    {
        return $user->isAdministrator();
    }

    public function publish(): void
    {
        $this->update([
            'is_published' => true,
            'published_at' => now(),
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'is_published' => false,
            'published_at' => null,
        ]);
    }

    public function isPublished(): bool
    {
        return $this->is_published;
    }
}
