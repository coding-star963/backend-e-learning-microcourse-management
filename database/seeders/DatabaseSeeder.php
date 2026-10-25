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
            'email' => 'admin.microlearn@gmail.com',
            'password' => 'password',
            'role' => User::ROLE_ADMINISTRATOR,
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

        // Create general-audience courses with lessons (5 courses, 3-5 lessons per course).
        // The administrator is the creator; enrollment is open to all learners.
        // Course durations are digits only, expressed in minutes.
        $courseData = [
            [
                'teacher_id' => $admin->id,
                'category_id' => $categories[0]->id,
                'title' => 'HTML & CSS Fundamentals',
                'description' => 'Learn the building blocks of the web: semantic HTML and modern CSS layouts.',
                'learning_objectives' => "Write semantic HTML pages\nStyle pages with CSS\nBuild responsive layouts with Flexbox",
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '60',
                'lessons' => [
                    ['title' => 'Introduction to HTML', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Working with Text & Links', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 700, 'order' => 2],
                    ['title' => 'Styling with CSS', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 3],
                    ['title' => 'Flexbox & Responsive Layouts', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 4],
                ],
            ],
            [
                'teacher_id' => $admin->id,
                'category_id' => $categories[0]->id,
                'title' => 'JavaScript Essentials',
                'description' => 'Variables, functions, and DOM manipulation to make web pages interactive.',
                'learning_objectives' => "Use variables, operators and conditions\nWrite reusable functions\nUpdate a web page with the DOM",
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '90',
                'lessons' => [
                    ['title' => 'Variables & Data Types', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 700, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Operators & Conditions', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 800, 'order' => 2],
                    ['title' => 'Functions & Scope', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 3],
                    ['title' => 'Arrays & Objects', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 4],
                    ['title' => 'DOM Manipulation', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 5],
                ],
            ],
            [
                'teacher_id' => $admin->id,
                'category_id' => $categories[1]->id,
                'title' => 'Programming Logic with Python',
                'description' => 'Think like a programmer: conditions, loops, and problem solving in Python.',
                'learning_objectives' => "Apply conditions and loops\nBreak problems into functions\nStore data in lists and dictionaries",
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '45',
                'lessons' => [
                    ['title' => 'Conditions & Loops', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 800, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Functions & Problem Solving', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'Lists & Dictionaries', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 850, 'order' => 3],
                ],
            ],
            [
                'teacher_id' => $admin->id,
                'category_id' => $categories[3]->id,
                'title' => 'Mobile Apps with Flutter',
                'description' => 'Build a simple cross-platform mobile app with Flutter widgets.',
                'learning_objectives' => "Compose screens from Flutter widgets\nNavigate between screens\nLoad data from a REST API",
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '75',
                'lessons' => [
                    ['title' => 'Flutter Widgets', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 900, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Layouts & Navigation', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 2],
                    ['title' => 'State Management Basics', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                    ['title' => 'Connecting to a REST API', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 4],
                ],
            ],
            [
                'teacher_id' => $admin->id,
                'category_id' => $categories[5]->id,
                'title' => 'UI/UX Design Principles',
                'description' => 'Design user-friendly screens using layout, color, and typography basics.',
                'learning_objectives' => "Apply basic design principles\nChoose readable colors and type\nCreate wireframes and prototypes",
                'status' => 'draft',
                'is_published' => false,
                'difficulty_level' => 'beginner',
                'duration' => '45',
                'lessons' => [
                    ['title' => 'Design Fundamentals', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 800, 'order' => 1],
                    ['title' => 'Color & Typography', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 700, 'order' => 2],
                    ['title' => 'Wireframing & Prototyping', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 3],
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

        // Create learners from the community, each enrolled in at least one course.
        // Learner accounts use the student role and Gmail registration rule.
        $students = [];
        $studentData = [
            ['name' => 'Alex Rivera', 'email' => 'alex.student@gmail.com', 'password' => 'password'],
            ['name' => 'James Wilson', 'email' => 'james.wilson@gmail.com', 'password' => 'password'],
            ['name' => 'Maria Garcia', 'email' => 'maria.garcia@gmail.com', 'password' => 'password'],
            ['name' => 'David Kim', 'email' => 'david.kim@gmail.com', 'password' => 'password'],
            ['name' => 'Lisa Anderson', 'email' => 'lisa.anderson@gmail.com', 'password' => 'password'],
            ['name' => 'Robert Taylor', 'email' => 'robert.taylor@gmail.com', 'password' => 'password'],
            ['name' => 'Jennifer Martinez', 'email' => 'jennifer.martinez@gmail.com', 'password' => 'password'],
            ['name' => 'Christopher Lee', 'email' => 'christopher.lee@gmail.com', 'password' => 'password'],
            ['name' => 'Amanda White', 'email' => 'amanda.white@gmail.com', 'password' => 'password'],
        ];

        foreach ($studentData as $sData) {
            $students[] = User::create(array_merge($sData, [
                'role' => User::ROLE_STUDENT,
                'email_verified_at' => now(),
            ]));
        }

        // Create enrollments with varied statuses and progress ('course' indexes $courses, published only)
        $enrollmentData = [
            ['user' => 0, 'course' => 0, 'status' => 'active', 'progress' => 25.0],
            ['user' => 1, 'course' => 0, 'status' => 'active', 'progress' => 75.0],
            ['user' => 1, 'course' => 1, 'status' => 'active', 'progress' => 40.0],
            ['user' => 2, 'course' => 0, 'status' => 'completed', 'progress' => 100],
            ['user' => 2, 'course' => 2, 'status' => 'active', 'progress' => 66.7],
            ['user' => 3, 'course' => 3, 'status' => 'active', 'progress' => 50.0],
            ['user' => 3, 'course' => 1, 'status' => 'active', 'progress' => 20.0],
            ['user' => 4, 'course' => 1, 'status' => 'completed', 'progress' => 100],
            ['user' => 4, 'course' => 2, 'status' => 'completed', 'progress' => 100],
            ['user' => 5, 'course' => 2, 'status' => 'suspended', 'progress' => 0],
            ['user' => 5, 'course' => 3, 'status' => 'active', 'progress' => 75.0],
            ['user' => 6, 'course' => 3, 'status' => 'completed', 'progress' => 100],
            ['user' => 7, 'course' => 0, 'status' => 'active', 'progress' => 0],
            ['user' => 8, 'course' => 0, 'status' => 'active', 'progress' => 50.0],
            ['user' => 8, 'course' => 2, 'status' => 'active', 'progress' => 33.3],
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

        // Create lesson progress data (the target percentage above decides how many lessons are done)
        $enrollments = Enrollment::with(['course.lessons'])->get();
        foreach ($enrollments as $enrollment) {
            $lessons = $enrollment->course->lessons;
            if ($lessons->isEmpty()) continue;

            $progressPercent = $enrollment->progress / 100;
            $lessonsToComplete = min($lessons->count(), max(0, (int) ceil($lessons->count() * $progressPercent)));

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

            // Store the same percentage and status the API computes from completed lessons.
            $enrollment->recalculateProgress(false);
        }

        // Create announcements
        $publishedCourses = Course::where('status', 'published')->get();

        $announcementData = [
            [
                'user_id' => $admin->id,
                'course_id' => null,
                'title' => 'Welcome to the Learning Platform',
                'content' => 'Everyone is welcome, including out-of-school youth and community learners. Explore practical micro-courses, enroll, and learn at your own pace.',
                'type' => 'general',
                'is_published' => true,
                'days_ago' => 10,
            ],
            [
                'user_id' => $admin->id,
                'course_id' => $publishedCourses[0]->id ?? null,
                'title' => 'New Lesson Added: Advanced Topics',
                'content' => 'A new lesson has been added to the course. Check it out to expand your knowledge.',
                'type' => 'course_update',
                'is_published' => true,
                'days_ago' => 5,
            ],
            [
                'user_id' => $admin->id,
                'course_id' => null,
                'title' => 'Scheduled Maintenance Notice',
                'content' => 'The platform will undergo scheduled maintenance this weekend. Please plan your learning sessions accordingly.',
                'type' => 'important',
                'is_published' => true,
                'days_ago' => 3,
            ],
            [
                'user_id' => $admin->id,
                'course_id' => $publishedCourses[1]->id ?? null,
                'title' => 'Course Update: New Resources Available',
                'content' => 'Additional learning resources have been uploaded to the course. Review them at your convenience.',
                'type' => 'course_update',
                'is_published' => false,
                'days_ago' => 1,
            ],
            [
                'user_id' => $admin->id,
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
