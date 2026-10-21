<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quiz extends Model
{
    protected $fillable = ['lesson_id', 'title', 'description', 'passing_score', 'is_published', 'revision', 'questions'];

    // Answer keys are only exposed deliberately by QuizResource to administrators.
    protected $hidden = ['questions'];

    protected $casts = ['questions' => 'array', 'is_published' => 'boolean', 'passing_score' => 'integer', 'revision' => 'integer'];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }
}
