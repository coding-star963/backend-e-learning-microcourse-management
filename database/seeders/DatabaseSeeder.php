<?php

namespace Database\Seeders;

use App\Models\Announcement;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
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

        // Create teachers
        $teacher1 = User::create([
            'name' => 'Sarah Johnson',
            'email' => 'teacher@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $teacher2 = User::create([
            'name' => 'Michael Chen',
            'email' => 'michael@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        $teacher3 = User::create([
            'name' => 'Emily Davis',
            'email' => 'emily@example.com',
            'password' => 'password',
            'role' => 'teacher',
            'email_verified_at' => now(),
        ]);

        // Create categories
        $categories = [];
        $categoryData = [
            ['name' => 'Web Development', 'description' => 'Building websites and web applications with HTML, CSS, and JavaScript'],
            ['name' => 'Programming Fundamentals', 'description' => 'Core programming concepts every developer should know'],
            ['name' => 'Data Science', 'description' => 'Analyzing and visualizing data to find useful insights'],
            ['name' => 'Mobile Development', 'description' => 'Creating apps for Android and iOS devices'],
            ['name' => 'Cybersecurity', 'description' => 'Protecting systems, networks, and data from threats'],
            ['name' => 'UI/UX Design', 'description' => 'Designing clear, usable, and attractive interfaces'],
        ];

        foreach ($categoryData as $cat) {
            $categories[] = Category::create($cat);
        }

        // Create courses with lessons (1-3 lessons per course)
        $courseData = [
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[0]->id,
                'title' => 'HTML & CSS Fundamentals',
                'description' => 'Learn the building blocks of the web: semantic HTML and modern CSS layouts.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '1 hour',
                'section' => 'BSIT 1-A',
                'lessons' => [
                    ['title' => 'Introduction to HTML', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Styling with CSS', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'Flexbox & Responsive Layouts', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                ],
            ],
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[0]->id,
                'title' => 'JavaScript Essentials',
                'description' => 'Variables, functions, and DOM manipulation to make web pages interactive.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '1 hour',
                'section' => 'BSIT 2-A',
                'lessons' => [
                    ['title' => 'Variables & Data Types', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 700, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Functions & DOM Manipulation', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 2],
                ],
            ],
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[1]->id,
                'title' => 'Programming Logic with Python',
                'description' => 'Think like a programmer: conditions, loops, and problem solving in Python.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '1 hour',
                'section' => 'BSCS 1-A',
                'lessons' => [
                    ['title' => 'Conditions & Loops', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 800, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Functions & Problem Solving', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                ],
            ],
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[2]->id,
                'title' => 'Data Analysis Basics',
                'description' => 'Clean, summarize, and chart data using spreadsheets and Python.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '45 minutes',
                'section' => 'BSCS 2-B',
                'lessons' => [
                    ['title' => 'Working with Datasets', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 900, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Charts & Visualization', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 2],
                ],
            ],
            [
                'teacher_id' => $teacher3->id,
                'category_id' => $categories[3]->id,
                'title' => 'Mobile Apps with Flutter',
                'description' => 'Build a simple cross-platform mobile app with Flutter widgets.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '1 hour',
                'section' => 'BSIT 3-A',
                'lessons' => [
                    ['title' => 'Flutter Widgets', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 900, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Layouts & Navigation', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 2],
                    ['title' => 'State Management Basics', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                ],
            ],
            [
                'teacher_id' => $teacher3->id,
                'category_id' => $categories[4]->id,
                'title' => 'Cybersecurity Awareness',
                'description' => 'Recognize common threats and practice safe habits online.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '30 minutes',
                'section' => 'BSIT 1-B',
                'lessons' => [
                    ['title' => 'Phishing & Password Safety', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 900, 'order' => 1, 'is_free_preview' => true],
                ],
            ],
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[5]->id,
                'title' => 'UI/UX Design Principles',
                'description' => 'Design user-friendly screens using layout, color, and typography basics.',
                'status' => 'draft',
                'is_published' => false,
                'difficulty_level' => 'beginner',
                'duration' => '45 minutes',
                'section' => 'BSIT 2-B',
                'lessons' => [
                    ['title' => 'Design Fundamentals', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 800, 'order' => 1],
                    ['title' => 'Wireframing & Prototyping', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                ],
            ],
        ];

        $courses = [];
        foreach ($courseData as $courseInfo) {
            $lessons = $courseInfo['lessons'] ?? [];
            unset($courseInfo['lessons']);

            $course = Course::create($courseInfo);
            $courses[] = $course;

            foreach ($lessons as $lessonInfo) {
                $lessonInfo['course_id'] = $course->id;
                Lesson::create($lessonInfo);
            }
        }

        // Create students (every seeded student is enrolled in at least one course)
        $students = [];
        $studentData = [
            ['name' => 'Alex Student', 'email' => 'student@example.com', 'password' => 'password'],
            ['name' => 'James Wilson', 'email' => 'james@example.com', 'password' => 'password'],
            ['name' => 'Maria Garcia', 'email' => 'maria@example.com', 'password' => 'password'],
            ['name' => 'David Kim', 'email' => 'david@example.com', 'password' => 'password'],
            ['name' => 'Lisa Anderson', 'email' => 'lisa@example.com', 'password' => 'password'],
            ['name' => 'Robert Taylor', 'email' => 'robert@example.com', 'password' => 'password'],
            ['name' => 'Jennifer Martinez', 'email' => 'jennifer@example.com', 'password' => 'password'],
            ['name' => 'Christopher Lee', 'email' => 'chris@example.com', 'password' => 'password'],
            ['name' => 'Amanda White', 'email' => 'amanda@example.com', 'password' => 'password'],
        ];

        foreach ($studentData as $sData) {
            $students[] = User::create(array_merge($sData, [
                'role' => 'student',
                'email_verified_at' => now(),
            ]));
        }

        // Create enrollments with varied statuses and progress ('course' indexes $courses, published only)
        $enrollmentData = [
            ['user' => 0, 'course' => 0, 'status' => 'active', 'progress' => 33.3],
            ['user' => 1, 'course' => 0, 'status' => 'active', 'progress' => 66.7],
            ['user' => 1, 'course' => 1, 'status' => 'active', 'progress' => 50.0],
            ['user' => 2, 'course' => 0, 'status' => 'completed', 'progress' => 100],
            ['user' => 2, 'course' => 2, 'status' => 'active', 'progress' => 50.0],
            ['user' => 3, 'course' => 3, 'status' => 'active', 'progress' => 50.0],
            ['user' => 3, 'course' => 4, 'status' => 'active', 'progress' => 33.3],
            ['user' => 4, 'course' => 1, 'status' => 'completed', 'progress' => 100],
            ['user' => 4, 'course' => 5, 'status' => 'completed', 'progress' => 100],
            ['user' => 5, 'course' => 2, 'status' => 'suspended', 'progress' => 0],
            ['user' => 5, 'course' => 4, 'status' => 'active', 'progress' => 66.7],
            ['user' => 6, 'course' => 3, 'status' => 'completed', 'progress' => 100],
            ['user' => 7, 'course' => 5, 'status' => 'active', 'progress' => 0],
            ['user' => 8, 'course' => 0, 'status' => 'active', 'progress' => 100],
            ['user' => 8, 'course' => 2, 'status' => 'active', 'progress' => 50.0],
        ];

        foreach ($enrollmentData as $eData) {
            $student = $students[$eData['user']];
            $course = $courses[$eData['course']];
            $enrolledAt = now()->subDays(rand(1, 60));
            $completedAt = $eData['status'] === 'completed' ? $enrolledAt->copy()->addDays(rand(7, 30)) : null;

            Enrollment::create([
                'user_id' => $student->id,
                'course_id' => $course->id,
                'status' => $eData['status'],
                'progress' => $eData['progress'],
                'enrolled_at' => $enrolledAt,
                'completed_at' => $completedAt,
                'last_accessed_at' => $eData['status'] === 'active' ? now()->subDays(rand(0, 5)) : null,
            ]);
        }

        // Create lesson progress data
        $enrollments = Enrollment::with(['course.lessons'])->get();
        foreach ($enrollments as $enrollment) {
            $lessons = $enrollment->course->lessons;
            if ($lessons->isEmpty()) continue;

            $progressPercent = $enrollment->progress / 100;
            $lessonsToComplete = (int) ceil($lessons->count() * $progressPercent);

            $lessonsToComplete = min($lessonsToComplete, $lessons->count());

            $lessonsToComplete = max(0, $lessonsToComplete);

            $lessonsToComplete = min($lessonsToComplete, $lessons->count());

            $lessons->take($lessonsToComplete)->each(function ($lesson) use ($enrollment) {
                $completedAt = $enrollment->enrolled_at->copy()->addDays(rand(1, 14));
                if ($completedAt->isAfter(now())) {
                    $completedAt = now()->subDays(rand(0, 3));
                }

                LessonProgress::create([
                    'user_id' => $enrollment->user_id,
                    'lesson_id' => $lesson->id,
                    'enrollment_id' => $enrollment->id,
                    'completed' => true,
                    'completed_at' => $completedAt,
                    'watch_duration_seconds' => $lesson->duration_seconds ?: rand(60, 600),
                ]);
            });
        }

        // Create announcements
        $teachers = User::where('role', 'teacher')->get();
        $publishedCourses = Course::where('status', 'published')->get();

        $announcementData = [
            [
                'user_id' => $teachers[0]->id,
                'course_id' => null,
                'title' => 'Welcome to the Learning Platform',
                'content' => 'We are excited to announce the launch of our new e-learning platform. Browse our courses and start learning today!',
                'type' => 'general',
                'is_published' => true,
                'days_ago' => 10,
            ],
            [
                'user_id' => $teachers[0]->id,
                'course_id' => $publishedCourses[0]->id ?? null,
                'title' => 'New Lesson Added: Advanced Topics',
                'content' => 'A new lesson has been added to the course. Check it out to expand your knowledge.',
                'type' => 'course_update',
                'is_published' => true,
                'days_ago' => 5,
            ],
            [
                'user_id' => $teachers[1]->id ?? $teachers[0]->id,
                'course_id' => null,
                'title' => 'Scheduled Maintenance Notice',
                'content' => 'The platform will undergo scheduled maintenance this weekend. Please plan your learning sessions accordingly.',
                'type' => 'important',
                'is_published' => true,
                'days_ago' => 3,
            ],
            [
                'user_id' => $teachers[0]->id,
                'course_id' => $publishedCourses[1]->id ?? null,
                'title' => 'Course Update: New Resources Available',
                'content' => 'Additional learning resources have been uploaded to the course. Review them at your convenience.',
                'type' => 'course_update',
                'is_published' => false,
                'days_ago' => 1,
            ],
            [
                'user_id' => $teachers[2]->id ?? $teachers[0]->id,
                'course_id' => null,
                'title' => 'Upcoming Webinar Series',
                'content' => 'Stay tuned for our upcoming webinar series on industry best practices. Registration details coming soon.',
                'type' => 'general',
                'is_published' => true,
                'days_ago' => 7,
            ],
        ];

        foreach ($announcementData as $data) {
            $daysAgo = $data['days_ago'];
            unset($data['days_ago']);

            $createdAt = now()->subDays($daysAgo);
            $publishedAt = $data['is_published'] ? $createdAt->copy()->addHours(rand(1, 5)) : null;

            Announcement::create(array_merge($data, [
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
                'published_at' => $publishedAt,
            ]));
        }
    }
}
