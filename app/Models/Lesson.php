<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lesson extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    const AVAILABILITY_FREE = 'free';
    const AVAILABILITY_LOCKED = 'locked';
    const AVAILABILITY_SCHEDULED = 'scheduled';

    protected $fillable = [
        'course_id',
        'title',
        'description',
        'status',
        'availability',
        'order',
        'video_path',
        'audio_path',
        'video_type',
        'audio_type',
        'video_size',
        'audio_size',
        'duration_seconds',
        'is_free_preview',
    ];

    protected $casts = [
        'order' => 'integer',
        'video_size' => 'integer',
        'audio_size' => 'integer',
        'duration_seconds' => 'integer',
        'is_free_preview' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LessonResource::class)->orderBy('order');
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function isFree(): bool
    {
        return $this->availability === self::AVAILABILITY_FREE;
    }

    public function isLocked(): bool
    {
        return $this->availability === self::AVAILABILITY_LOCKED;
    }

    public function publish(): void
    {
        $this->update(['status' => self::STATUS_PUBLISHED]);
    }

    public function unpublish(): void
    {
        $this->update(['status' => self::STATUS_DRAFT]);
    }

    public function archive(): void
    {
        $this->update(['status' => self::STATUS_ARCHIVED]);
    }

    public function getVideoUrlAttribute(): ?string
    {
        return $this->video_path ? asset('storage/' . $this->video_path) : null;
    }

    public function getAudioUrlAttribute(): ?string
    {
        return $this->audio_path ? asset('storage/' . $this->audio_path) : null;
    }

    public function getDurationFormattedAttribute(): ?string
    {
        if (!$this->duration_seconds) {
            return null;
        }
        $minutes = floor($this->duration_seconds / 60);
        $seconds = $this->duration_seconds % 60;
        return $minutes > 0 ? "{$minutes}m {$seconds}s" : "{$seconds}s";
    }

    public static function getNextOrder(int $courseId): int
    {
        return (self::where('course_id', $courseId)->max('order') ?? 0) + 1;
    }
}
