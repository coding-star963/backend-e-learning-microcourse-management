<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create administrator
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'role' => 'administrator',
            'email_verified_at' => now(),
        ]);

        // Create teacher
        $teacher = User::create([
            'name' => 'Teacher User',
            'email' => 'teacher@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        // Create additional test users
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'role' => 'teacher',
        ]);

        // Create categories
        $categories = [
            ['name' => 'Web Development', 'description' => 'Courses about web technologies and frameworks'],
            ['name' => 'Data Science', 'description' => 'Courses about data analysis, ML, and AI'],
            ['name' => 'Mobile Development', 'description' => 'Courses about building mobile applications'],
            ['name' => 'Cybersecurity', 'description' => 'Courses about security practices and ethical hacking'],
            ['name' => 'Cloud Computing', 'description' => 'Courses about cloud platforms and DevOps'],
            ['name' => 'Design', 'description' => 'Courses about UI/UX design and graphic design'],
        ];

        $createdCategories = [];
        foreach ($categories as $category) {
            $createdCategories[] = Category::create($category);
        }

        // Create sample courses
        $courses = [
            [
                'teacher_id' => $teacher->id,
                'category_id' => $createdCategories[0]->id,
                'title' => 'HTML & CSS Fundamentals',
                'description' => 'Learn the building blocks of web development with HTML and CSS.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '2 hours',
            ],
            [
                'teacher_id' => $teacher->id,
                'category_id' => $createdCategories[0]->id,
                'title' => 'JavaScript Essentials',
                'description' => 'Master JavaScript fundamentals and modern ES6+ features.',
                'status' => 'draft',
                'is_published' => false,
                'difficulty_level' => 'intermediate',
                'duration' => '3 hours',
            ],
            [
                'teacher_id' => $teacher->id,
                'category_id' => $createdCategories[1]->id,
                'title' => 'Introduction to Python for Data Science',
                'description' => 'Get started with Python programming for data analysis.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '4 hours',
            ],
            [
                'teacher_id' => $teacher->id,
                'category_id' => $createdCategories[2]->id,
                'title' => 'React Native Crash Course',
                'description' => 'Build your first mobile app with React Native.',
                'status' => 'archived',
                'is_published' => false,
                'difficulty_level' => 'intermediate',
                'duration' => '5 hours',
            ],
        ];

        foreach ($courses as $course) {
            Course::create($course);
        }
    }
}
