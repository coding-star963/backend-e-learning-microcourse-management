<?php

namespace App\Models;

use App\Support\MediaUrl;

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
        'learning_objectives',
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
            $course->slug = static::uniqueSlug($course->title);
        });

        static::updating(function (Course $course) {
            if ($course->isDirty('title')) {
                $course->slug = static::uniqueSlug($course->title, $course->id);
            }
        });
    }

    /**
     * Build a slug that is not used by another course ("intro", "intro-2", ...),
     * so two courses with the same title do not violate the unique index.
     */
    public static function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'course';
        $slug = $base;
        $suffix = 2;

        while (static::where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * Learning objectives as a list (stored one per line).
     *
     * @return array<int, string>
     */
    public function getLearningObjectivesListAttribute(): array
    {
        return collect(preg_split('/\r\n|\r|\n/', (string) $this->learning_objectives))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        return MediaUrl::publicUrl($this->thumbnail);
    }

    // Legacy course creator relation; ownership does not grant permissions.
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

    /**
     * Lessons students can see; progress percentages are based on these.
     */
    public function publishedLessons(): HasMany
    {
        return $this->hasMany(Lesson::class)
            ->where('status', Lesson::STATUS_PUBLISHED)
            ->orderBy('order');
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

    /**
     * Keep stored enrollment progress in step after the set of published
     * lessons changes (lesson published, unpublished, archived or deleted).
     */
    public function refreshEnrollmentProgress(): void
    {
        // A deactivated student's records are frozen until the account is
        // activated again (suspended students keep being updated).
        $this->enrollments()
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])
            ->whereHas('user', fn ($q) => $q->where('is_active', true))
            ->get()
            ->each(fn (Enrollment $enrollment) => $enrollment->setRelation('course', $this)->recalculateProgress(false));
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

    /**
     * Administrators manage every course.
     */
    public function isManageableBy(User $user): bool
    {
        return $user->isAdministrator();
    }

    /**
     * Students see published courses, plus any course they are enrolled in.
     */
    public function isVisibleTo(User $user): bool
    {
        if ($this->isManageableBy($user)) {
            return true;
        }

        if (! $user->isStudent()) {
            return false;
        }

        return $this->isPublished()
            || $this->enrollments()->where('user_id', $user->id)->exists();
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve {course} by slug (web app) or by numeric id (mobile app).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }

        return static::where('slug', $value)->first()
            ?? (ctype_digit((string) $value) ? static::find($value) : null);
    }
}
