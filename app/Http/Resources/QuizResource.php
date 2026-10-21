<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class QuizResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = $request->user()->isAdministrator();

        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,
            'title' => $this->title,
            'description' => $this->description,
            'passing_score' => $this->passing_score,
            'is_published' => $this->is_published,
            'revision' => $this->revision,
            'questions' => array_map(fn ($question) => $admin ? $question : [
                'question' => $question['question'],
                'options' => $question['options'],
            ], $this->questions),
        ];
    }
}
