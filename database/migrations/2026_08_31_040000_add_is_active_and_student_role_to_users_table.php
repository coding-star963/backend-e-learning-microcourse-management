<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('profile_photo');
        });

        // Update the role enum to include 'student' (MySQL only — SQLite stores enum as text)
        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('administrator', 'teacher', 'student') DEFAULT 'teacher'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        if (config('database.default') !== 'sqlite') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('administrator', 'teacher') DEFAULT 'teacher'");
        }
    }
};
