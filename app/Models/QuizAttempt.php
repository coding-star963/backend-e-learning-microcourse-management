<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAttempt extends Model
{
    protected $fillable = ['quiz_id', 'user_id', 'revision', 'quiz_title', 'correct_count', 'question_count', 'score', 'passing_score', 'passed', 'results'];

    protected $casts = ['results' => 'array', 'score' => 'float', 'passed' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
