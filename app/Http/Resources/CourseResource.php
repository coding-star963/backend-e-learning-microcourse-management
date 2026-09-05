<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CourseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail ? asset('storage/' . $this->thumbnail) : null,
            'status' => $this->status,
            'is_published' => $this->is_published,
            'difficulty_level' => $this->difficulty_level,
            'duration' => $this->duration,
            'teacher' => new UserResource($this->whenLoaded('teacher')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
