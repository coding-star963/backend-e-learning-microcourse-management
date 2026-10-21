<?php

namespace App\Models;

use App\Support\MediaUrl;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'suspended_at', 'profile_photo'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasApiTokens, Notifiable;

    const ROLE_ADMINISTRATOR = 'administrator';
    const ROLE_STUDENT = 'student';

    protected $attributes = ['role' => self::ROLE_STUDENT];

    // Account status: deactivated (is_active = false) wins over suspended (suspended_at set).
    const ACCOUNT_ACTIVE = 'active';
    const ACCOUNT_SUSPENDED = 'suspended';
    const ACCOUNT_DEACTIVATED = 'deactivated';

    const SUSPENDED_MESSAGE = 'Your account has been suspended. Please contact an administrator to have it reactivated.';
    const DEACTIVATED_MESSAGE = 'Your account has been deactivated. Please contact an administrator.';

    // A deactivated account's records are frozen; suspended accounts can still be changed.
    const FROZEN_MESSAGE = 'This account is deactivated, so its records cannot be changed. Activate the account first.';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * Check if user is an administrator.
     */
    public function isAdministrator(): bool
    {
        return $this->role === self::ROLE_ADMINISTRATOR;
    }

    /**
     * Check if user is a student.
     */
    public function isStudent(): bool
    {
        return $this->role === self::ROLE_STUDENT;
    }

    /**
     * Check if user account is active.
     */
    public function isActive(): bool
    {
        return $this->is_active === true;
    }

    /**
     * Check if user account is inactive.
     */
    public function isInactive(): bool
    {
        return $this->is_active === false;
    }

    /**
     * Check if user account is suspended (and not deactivated).
     */
    public function isSuspended(): bool
    {
        return $this->is_active !== false && $this->suspended_at !== null;
    }

    /**
     * active, suspended or deactivated.
     */
    public function accountStatus(): string
    {
        if ($this->isInactive()) {
            return self::ACCOUNT_DEACTIVATED;
        }

        return $this->suspended_at !== null ? self::ACCOUNT_SUSPENDED : self::ACCOUNT_ACTIVE;
    }

    /**
     * Filter users by account status (active, suspended or deactivated).
     */
    public function scopeWithAccountStatus(Builder $query, string $status): void
    {
        match ($status) {
            self::ACCOUNT_DEACTIVATED => $query->where('is_active', false),
            self::ACCOUNT_SUSPENDED => $query->where('is_active', true)->whereNotNull('suspended_at'),
            default => $query->where('is_active', true)->whereNull('suspended_at'),
        };
    }

    /**
     * Bring the student's stored progress up to date, e.g. after reactivation
     * (course changes made while the account was deactivated were skipped).
     */
    public function refreshEnrollmentProgress(): void
    {
        $this->enrollments()
            ->whereIn('status', [Enrollment::STATUS_ACTIVE, Enrollment::STATUS_COMPLETED])
            ->with('course')
            ->get()
            ->each(fn (Enrollment $enrollment) => $enrollment->recalculateProgress(false));
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'teacher_id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function enrolledCourses()
    {
        return $this->belongsToMany(Course::class, 'enrollments')
            ->withPivot('status', 'progress', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }

    public function lessonProgress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    /**
     * Get the profile photo URL.
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return MediaUrl::publicUrl($this->profile_photo);
    }
}
