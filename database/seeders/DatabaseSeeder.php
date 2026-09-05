<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
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

        // Create student
        User::create([
            'name' => 'Alex Student',
            'email' => 'student@example.com',
            'password' => 'password',
            'role' => 'student',
            'email_verified_at' => now(),
        ]);

        // Create categories
        $categories = [];
        $categoryData = [
            ['name' => 'Web Development', 'description' => 'Courses about web technologies, frameworks, and modern development practices'],
            ['name' => 'Data Science', 'description' => 'Courses about data analysis, machine learning, and artificial intelligence'],
            ['name' => 'Mobile Development', 'description' => 'Courses about building iOS, Android, and cross-platform mobile applications'],
            ['name' => 'Cybersecurity', 'description' => 'Courses about security practices, ethical hacking, and threat prevention'],
            ['name' => 'Cloud Computing', 'description' => 'Courses about AWS, Azure, GCP, and DevOps practices'],
            ['name' => 'UI/UX Design', 'description' => 'Courses about user interface and user experience design principles'],
            ['name' => 'Programming Fundamentals', 'description' => 'Core programming concepts and computer science basics'],
            ['name' => 'DevOps', 'description' => 'Development operations, CI/CD, and infrastructure management'],
            ['name' => 'Blockchain', 'description' => 'Courses about distributed ledger technology and Web3'],
            ['name' => 'Game Development', 'description' => 'Courses about creating video games and interactive experiences'],
        ];

        foreach ($categoryData as $cat) {
            $categories[] = Category::create($cat);
        }

        // Create courses with lessons
        $courseData = [
            // Web Development courses
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[0]->id,
                'title' => 'HTML & CSS Fundamentals',
                'description' => 'Master the building blocks of web development. Learn HTML5 semantic elements, CSS3 flexbox, grid, and responsive design principles.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '4 hours',
                'lessons' => [
                    ['title' => 'Introduction to HTML', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'HTML Document Structure', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'Working with Text & Links', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 750, 'order' => 3],
                    ['title' => 'HTML Forms & Inputs', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 4],
                    ['title' => 'Introduction to CSS', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 800, 'order' => 5],
                    ['title' => 'CSS Flexbox Layout', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 6],
                    ['title' => 'CSS Grid Layout', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 7],
                    ['title' => 'Responsive Design', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 950, 'order' => 8],
                ],
            ],
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[0]->id,
                'title' => 'JavaScript Essentials',
                'description' => 'Deep dive into JavaScript. Learn variables, functions, DOM manipulation, async programming, and modern ES6+ features.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '6 hours',
                'lessons' => [
                    ['title' => 'Variables & Data Types', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 700, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Functions & Scope', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'Arrays & Objects', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                    ['title' => 'DOM Manipulation', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 4],
                    ['title' => 'Events & Event Handling', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 850, 'order' => 5],
                    ['title' => 'Promises & Async/Await', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 6],
                    ['title' => 'ES6+ Features', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 950, 'order' => 7],
                ],
            ],
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[0]->id,
                'title' => 'React.js Complete Guide',
                'description' => 'Build modern web applications with React. Learn components, hooks, state management, routing, and best practices.',
                'status' => 'draft',
                'is_published' => false,
                'difficulty_level' => 'intermediate',
                'duration' => '8 hours',
                'lessons' => [
                    ['title' => 'Introduction to React', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1],
                    ['title' => 'Components & JSX', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 800, 'order' => 2],
                    ['title' => 'Props & State', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 3],
                    ['title' => 'useEffect & Side Effects', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 4],
                    ['title' => 'React Router', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 750, 'order' => 5],
                ],
            ],
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[0]->id,
                'title' => 'Node.js & Express Backend',
                'description' => 'Create robust backend APIs with Node.js and Express. Learn routing, middleware, authentication, and database integration.',
                'status' => 'archived',
                'is_published' => false,
                'difficulty_level' => 'intermediate',
                'duration' => '5 hours',
                'lessons' => [
                    ['title' => 'Node.js Basics', 'status' => 'archived', 'availability' => 'free', 'duration_seconds' => 700, 'order' => 1],
                    ['title' => 'Express Framework', 'status' => 'archived', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'RESTful API Design', 'status' => 'archived', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                ],
            ],
            // Data Science courses
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[1]->id,
                'title' => 'Python for Data Science',
                'description' => 'Start your data science journey with Python. Learn NumPy, Pandas, data visualization, and basic statistics.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '6 hours',
                'lessons' => [
                    ['title' => 'Python Basics Review', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 500, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'NumPy Fundamentals', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 2],
                    ['title' => 'Pandas DataFrames', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1500, 'order' => 3],
                    ['title' => 'Data Cleaning Techniques', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 4],
                    ['title' => 'Matplotlib & Seaborn', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1300, 'order' => 5],
                    ['title' => 'Statistical Analysis', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1400, 'order' => 6],
                ],
            ],
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[1]->id,
                'title' => 'Machine Learning Fundamentals',
                'description' => 'Understand machine learning algorithms. Linear regression, classification, clustering, and model evaluation.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'advanced',
                'duration' => '10 hours',
                'lessons' => [
                    ['title' => 'What is Machine Learning?', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Linear Regression', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 2],
                    ['title' => 'Logistic Regression', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 3],
                    ['title' => 'Decision Trees & Random Forests', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1400, 'order' => 4],
                    ['title' => 'Support Vector Machines', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1300, 'order' => 5],
                    ['title' => 'K-Means Clustering', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 6],
                    ['title' => 'Model Evaluation Metrics', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 7],
                ],
            ],
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[1]->id,
                'title' => 'Deep Learning with TensorFlow',
                'description' => 'Build neural networks with TensorFlow and Keras. CNNs, RNNs, and transfer learning.',
                'status' => 'draft',
                'is_published' => false,
                'difficulty_level' => 'advanced',
                'duration' => '12 hours',
                'lessons' => [
                    ['title' => 'Neural Network Basics', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 800, 'order' => 1],
                    ['title' => 'Building Your First Neural Network', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 2],
                    ['title' => 'Convolutional Neural Networks', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1500, 'order' => 3],
                    ['title' => 'Recurrent Neural Networks', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1400, 'order' => 4],
                ],
            ],
            // Mobile Development courses
            [
                'teacher_id' => $teacher3->id,
                'category_id' => $categories[2]->id,
                'title' => 'React Native Crash Course',
                'description' => 'Build cross-platform mobile apps with React Native. Learn navigation, state, APIs, and deployment.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '7 hours',
                'lessons' => [
                    ['title' => 'React Native Setup', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Core Components', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'Navigation with React Navigation', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 3],
                    ['title' => 'State Management', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 4],
                    ['title' => 'Working with APIs', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 800, 'order' => 5],
                ],
            ],
            [
                'teacher_id' => $teacher3->id,
                'category_id' => $categories[2]->id,
                'title' => 'Flutter Development Bootcamp',
                'description' => 'Learn Flutter and Dart from scratch. Build beautiful, natively compiled applications.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '8 hours',
                'lessons' => [
                    ['title' => 'Dart Language Basics', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 700, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Flutter Widgets', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 2],
                    ['title' => 'Layouts & Responsive Design', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 3],
                    ['title' => 'State Management in Flutter', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 4],
                    ['title' => 'Networking & APIs', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 850, 'order' => 5],
                    ['title' => 'Firebase Integration', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 6],
                ],
            ],
            // Cybersecurity courses
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[3]->id,
                'title' => 'Ethical Hacking Fundamentals',
                'description' => 'Learn ethical hacking techniques. Penetration testing, vulnerability assessment, and security auditing.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '9 hours',
                'lessons' => [
                    ['title' => 'Introduction to Ethical Hacking', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 500, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Reconnaissance & OSINT', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 2],
                    ['title' => 'Scanning Networks', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                    ['title' => 'Vulnerability Analysis', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 4],
                    ['title' => 'System Hacking', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1300, 'order' => 5],
                ],
            ],
            // Cloud Computing courses
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[4]->id,
                'title' => 'AWS Cloud Practitioner',
                'description' => 'Prepare for the AWS Cloud Practitioner certification. Learn core AWS services and cloud concepts.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '5 hours',
                'lessons' => [
                    ['title' => 'Cloud Computing Basics', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'AWS Core Services', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 2],
                    ['title' => 'AWS Security & Compliance', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 3],
                    ['title' => 'AWS Pricing & Support', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 700, 'order' => 4],
                ],
            ],
            // UI/UX Design courses
            [
                'teacher_id' => $teacher3->id,
                'category_id' => $categories[5]->id,
                'title' => 'UI Design Fundamentals',
                'description' => 'Learn the principles of great user interface design. Color theory, typography, layout, and design systems.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '4 hours',
                'lessons' => [
                    ['title' => 'Introduction to UI Design', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 500, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Color Theory', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 800, 'order' => 2],
                    ['title' => 'Typography Fundamentals', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 700, 'order' => 3],
                    ['title' => 'Layout & Composition', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 4],
                    ['title' => 'Design Systems', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 5],
                ],
            ],
            [
                'teacher_id' => $teacher3->id,
                'category_id' => $categories[5]->id,
                'title' => 'UX Research Methods',
                'description' => 'Master user experience research. User interviews, usability testing, surveys, and analytics.',
                'status' => 'draft',
                'is_published' => false,
                'difficulty_level' => 'intermediate',
                'duration' => '5 hours',
                'lessons' => [
                    ['title' => 'Introduction to UX Research', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1],
                    ['title' => 'User Interviews', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'Usability Testing', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                ],
            ],
            // Programming Fundamentals
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[6]->id,
                'title' => 'Computer Science 101',
                'description' => 'Core computer science concepts. Algorithms, data structures, Big O notation, and computational thinking.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'beginner',
                'duration' => '8 hours',
                'lessons' => [
                    ['title' => 'What is Computer Science?', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 400, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Binary & Number Systems', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 700, 'order' => 2],
                    ['title' => 'Algorithms Introduction', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                    ['title' => 'Data Structures Overview', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 4],
                    ['title' => 'Big O Notation', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 5],
                    ['title' => 'Sorting Algorithms', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 6],
                ],
            ],
            // DevOps courses
            [
                'teacher_id' => $teacher1->id,
                'category_id' => $categories[7]->id,
                'title' => 'Docker & Kubernetes',
                'description' => 'Containerize applications with Docker and orchestrate with Kubernetes. Production-ready deployments.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '7 hours',
                'lessons' => [
                    ['title' => 'Introduction to Containers', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'Docker Basics', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 2],
                    ['title' => 'Dockerfile & Images', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 3],
                    ['title' => 'Docker Compose', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 4],
                    ['title' => 'Kubernetes Fundamentals', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1300, 'order' => 5],
                ],
            ],
            // Blockchain courses
            [
                'teacher_id' => $teacher2->id,
                'category_id' => $categories[8]->id,
                'title' => 'Blockchain & Cryptocurrency',
                'description' => 'Understand blockchain technology. Bitcoin, Ethereum, smart contracts, and decentralized applications.',
                'status' => 'draft',
                'is_published' => false,
                'difficulty_level' => 'intermediate',
                'duration' => '6 hours',
                'lessons' => [
                    ['title' => 'What is Blockchain?', 'status' => 'draft', 'availability' => 'free', 'duration_seconds' => 500, 'order' => 1],
                    ['title' => 'How Bitcoin Works', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 900, 'order' => 2],
                    ['title' => 'Ethereum & Smart Contracts', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 3],
                ],
            ],
            // Game Development courses
            [
                'teacher_id' => $teacher3->id,
                'category_id' => $categories[9]->id,
                'title' => 'Unity Game Development',
                'description' => 'Create 2D and 3D games with Unity. C# scripting, physics, animations, and publishing.',
                'status' => 'published',
                'is_published' => true,
                'difficulty_level' => 'intermediate',
                'duration' => '10 hours',
                'lessons' => [
                    ['title' => 'Unity Interface Overview', 'status' => 'published', 'availability' => 'free', 'duration_seconds' => 600, 'order' => 1, 'is_free_preview' => true],
                    ['title' => 'C# for Unity', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1200, 'order' => 2],
                    ['title' => 'Game Objects & Components', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1000, 'order' => 3],
                    ['title' => 'Physics & Collisions', 'status' => 'published', 'availability' => 'locked', 'duration_seconds' => 1100, 'order' => 4],
                    ['title' => '2D Game Mechanics', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1300, 'order' => 5],
                    ['title' => '3D Game Mechanics', 'status' => 'draft', 'availability' => 'locked', 'duration_seconds' => 1400, 'order' => 6],
                ],
            ],
        ];

        foreach ($courseData as $courseInfo) {
            $lessons = $courseInfo['lessons'] ?? [];
            unset($courseInfo['lessons']);

            $course = Course::create($courseInfo);

            foreach ($lessons as $lessonInfo) {
                $lessonInfo['course_id'] = $course->id;
                Lesson::create($lessonInfo);
            }
        }

        // Create additional students for enrollment data
        $students = [];
        $studentData = [
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

        // Get all published courses
        $publishedCourses = Course::where('status', 'published')->get();

        // Create enrollments with varied statuses and progress
        $enrollmentData = [
            // James Wilson enrollments
            ['user' => 0, 'course' => 0, 'status' => 'active', 'progress' => 75.5],
            ['user' => 0, 'course' => 1, 'status' => 'active', 'progress' => 45.0],
            ['user' => 0, 'course' => 4, 'status' => 'completed', 'progress' => 100],
            ['user' => 0, 'course' => 7, 'status' => 'active', 'progress' => 30.0],

            // Maria Garcia enrollments
            ['user' => 1, 'course' => 0, 'status' => 'active', 'progress' => 90.0],
            ['user' => 1, 'course' => 2, 'status' => 'active', 'progress' => 60.0],
            ['user' => 1, 'course' => 4, 'status' => 'active', 'progress' => 25.0],
            ['user' => 1, 'course' => 8, 'status' => 'completed', 'progress' => 100],

            // David Kim enrollments
            ['user' => 2, 'course' => 0, 'status' => 'active', 'progress' => 55.0],
            ['user' => 2, 'course' => 1, 'status' => 'active', 'progress' => 80.0],
            ['user' => 2, 'course' => 3, 'status' => 'cancelled', 'progress' => 10.0],
            ['user' => 2, 'course' => 5, 'status' => 'active', 'progress' => 40.0],

            // Lisa Anderson enrollments
            ['user' => 3, 'course' => 0, 'status' => 'completed', 'progress' => 100],
            ['user' => 3, 'course' => 1, 'status' => 'active', 'progress' => 70.0],
            ['user' => 3, 'course' => 4, 'status' => 'active', 'progress' => 50.0],
            ['user' => 3, 'course' => 6, 'status' => 'active', 'progress' => 35.0],

            // Robert Taylor enrollments
            ['user' => 4, 'course' => 0, 'status' => 'active', 'progress' => 65.0],
            ['user' => 4, 'course' => 2, 'status' => 'suspended', 'progress' => 20.0],
            ['user' => 4, 'course' => 5, 'status' => 'active', 'progress' => 55.0],

            // Jennifer Martinez enrollments
            ['user' => 5, 'course' => 0, 'status' => 'active', 'progress' => 85.0],
            ['user' => 5, 'course' => 1, 'status' => 'active', 'progress' => 40.0],
            ['user' => 5, 'course' => 4, 'status' => 'completed', 'progress' => 100],
            ['user' => 5, 'course' => 7, 'status' => 'active', 'progress' => 60.0],

            // Christopher Lee enrollments
            ['user' => 6, 'course' => 0, 'status' => 'active', 'progress' => 50.0],
            ['user' => 6, 'course' => 1, 'status' => 'active', 'progress' => 30.0],
            ['user' => 6, 'course' => 3, 'status' => 'active', 'progress' => 75.0],

            // Amanda White enrollments
            ['user' => 7, 'course' => 0, 'status' => 'active', 'progress' => 95.0],
            ['user' => 7, 'course' => 2, 'status' => 'active', 'progress' => 65.0],
            ['user' => 7, 'course' => 4, 'status' => 'completed', 'progress' => 100],
            ['user' => 7, 'course' => 6, 'status' => 'active', 'progress' => 45.0],
        ];

        foreach ($enrollmentData as $eData) {
            $student = $students[$eData['user']];
            $course = $publishedCourses[$eData['course']] ?? $publishedCourses[0];
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
    }
}
