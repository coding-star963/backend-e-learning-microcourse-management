<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonProgressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'lesson_id' => $this->lesson_id,
            'enrollment_id' => $this->enrollment_id,
            'completed' => $this->completed,
            'completed_at' => $this->completed_at,
            'watch_duration_seconds' => $this->watch_duration_seconds,
            'lesson' => [
                'id' => $this->lesson->id,
                'title' => $this->lesson->title,
                'order' => $this->lesson->order,
                'duration_seconds' => $this->lesson->duration_seconds,
                'duration_formatted' => $this->lesson->duration_formatted,
                'course' => [
                    'id' => $this->lesson->course->id,
                    'title' => $this->lesson->course->title,
                    'slug' => $this->lesson->course->slug,
                ],
            ],
            'enrollment' => [
                'id' => $this->enrollment->id,
                'status' => $this->enrollment->status,
                'progress' => $this->enrollment->progress,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
