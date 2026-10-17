<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Set by LessonController for students who cannot open this lesson yet.
        $mediaLocked = (bool) ($this->resource->getAttributes()['media_locked'] ?? false);

        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'availability' => $this->availability,
            'available_at' => $this->available_at,
            // Whether students can open the lesson right now (4.6).
            'is_available' => $this->resource->isAvailable(),
            'order' => $this->order,
            'video_url' => $mediaLocked ? null : $this->video_url,
            'audio_url' => $mediaLocked ? null : $this->audio_url,
            'video_type' => $this->video_type,
            'audio_type' => $this->audio_type,
            'video_size' => $this->video_size,
            'audio_size' => $this->audio_size,
            'duration_seconds' => $this->duration_seconds,
            'duration_formatted' => $this->duration_formatted,
            'is_free_preview' => $this->is_free_preview,
            // Only present for students: whether they completed this lesson.
            'is_completed' => $this->when(
                array_key_exists('is_completed', $this->resource->getAttributes()),
                fn () => (bool) $this->resource->getAttribute('is_completed'),
            ),
            'resources' => $mediaLocked ? [] : LessonResourceResource::collection($this->whenLoaded('resources')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
