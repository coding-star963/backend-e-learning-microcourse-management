<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Course extends Model
{
    use HasFactory;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'teacher_id',
        'category_id',
        'title',
        'slug',
        'description',
        'thumbnail',
        'status',
        'is_published',
        'difficulty_level',
        'duration',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Course $course) {
            $course->slug = Str::slug($course->title);
        });

        static::updating(function (Course $course) {
            if ($course->isDirty('title')) {
                $course->slug = Str::slug($course->title);
            }
        });
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class)->orderBy('order');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function enrolledUsers()
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot('status', 'progress', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }

    public function enrollmentsCount(): int
    {
        return $this->enrollments()->count();
    }

    public function activeEnrollmentsCount(): int
    {
        return $this->enrollments()->where('status', 'active')->count();
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->is_published;
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isArchived(): bool
    {
        return $this->status === self::STATUS_ARCHIVED;
    }

    public function publish(): void
    {
        $this->update([
            'status' => self::STATUS_PUBLISHED,
            'is_published' => true,
        ]);
    }

    public function unpublish(): void
    {
        $this->update([
            'status' => self::STATUS_DRAFT,
            'is_published' => false,
        ]);
    }

    public function archive(): void
    {
        $this->update([
            'status' => self::STATUS_ARCHIVED,
            'is_published' => false,
        ]);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
