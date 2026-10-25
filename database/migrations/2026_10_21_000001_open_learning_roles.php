<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $retiredIds = DB::table('users')->where('role', 'teacher')->pluck('id');
        DB::table('personal_access_tokens')->where('tokenable_type', \App\Models\User::class)
            ->whereIn('tokenable_id', $retiredIds)->delete();
        DB::table('users')->whereIn('id', $retiredIds)->update(['role' => 'student', 'updated_at' => now()]);

        // Keep course ownership/history intact; it no longer grants management access.
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('administrator', 'student') NOT NULL DEFAULT 'student'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('administrator', 'teacher', 'student') NOT NULL DEFAULT 'teacher'");
        }
        // Converting learners back to teachers would require guessing their former roles.
    }
};
