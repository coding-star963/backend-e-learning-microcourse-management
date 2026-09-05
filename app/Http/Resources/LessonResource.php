<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'course_id' => $this->course_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'availability' => $this->availability,
            'order' => $this->order,
            'video_url' => $this->video_url,
            'audio_url' => $this->audio_url,
            'video_type' => $this->video_type,
            'audio_type' => $this->audio_type,
            'video_size' => $this->video_size,
            'audio_size' => $this->audio_size,
            'duration_seconds' => $this->duration_seconds,
            'duration_formatted' => $this->duration_formatted,
            'is_free_preview' => $this->is_free_preview,
            'resources' => LessonResourceResource::collection($this->whenLoaded('resources')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
