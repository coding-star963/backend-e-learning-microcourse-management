<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->enum('availability', ['free', 'locked', 'scheduled'])->default('free');
            $table->unsignedInteger('order')->default(0);
            $table->string('video_path')->nullable();
            $table->string('audio_path')->nullable();
            $table->string('video_type')->nullable();
            $table->string('audio_type')->nullable();
            $table->unsignedInteger('video_size')->nullable();
            $table->unsignedInteger('audio_size')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_free_preview')->default(false);
            $table->timestamps();

            $table->index(['course_id', 'order']);
            $table->index(['course_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
