<?php

use App\Http\Controllers\Announcement\AnnouncementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Enrollment\EnrollmentController;
use App\Http\Controllers\Lesson\LessonController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Sync\SyncController;
use App\Http\Controllers\Progress\ProgressController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\Teacher\TeacherController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);
// Sign-up and reset (an email alone unlocks it) are public, so cap attempts per IP.
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
});


// Protected routes
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // Live sync: a cheap change marker the web portal and mobile app poll.
    Route::get('/sync', [SyncController::class, 'show']);

    // The signed-in user's personal notices (e.g. enrollment changes).
    Route::get('/notifications', [NotificationController::class, 'index']);

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // User management routes (administrator only)
    Route::middleware('role:administrator')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    });

    // Account suspension: administrators for any other account, teachers for
    // students in their own courses (checked in the controller).
    Route::middleware('role:administrator,teacher')->prefix('users')->group(function () {
        Route::post('/{user}/suspend', [UserController::class, 'suspend']);
        Route::post('/{user}/unsuspend', [UserController::class, 'unsuspend']);
    });

    // Teacher management (administrator only)
    Route::middleware('role:administrator')->get('/teachers', [TeacherController::class, 'index']);

    // Profile routes
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::post('/photo', [ProfileController::class, 'updatePhoto']);
        Route::delete('/photo', [ProfileController::class, 'deletePhoto']);
    });

    // Category routes
    Route::prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::middleware('role:administrator,teacher')->post('/', [CategoryController::class, 'store']);
        // Categories are shared by every teacher's courses, so only administrators change or remove them.
        Route::middleware('role:administrator')->group(function () {
            Route::put('/{category}', [CategoryController::class, 'update']);
            Route::delete('/{category}', [CategoryController::class, 'destroy']);
        });
    });

    // Course routes
    Route::prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::get('/categories', [CourseController::class, 'categories']);
        Route::get('/{course}', [CourseController::class, 'show']);
        Route::middleware('role:administrator,teacher')->group(function () {
            Route::post('/', [CourseController::class, 'store']);
            Route::put('/{course}', [CourseController::class, 'update']);
            Route::delete('/{course}', [CourseController::class, 'destroy']);
            Route::post('/{course}/publish', [CourseController::class, 'publish']);
            Route::post('/{course}/unpublish', [CourseController::class, 'unpublish']);
            Route::post('/{course}/archive', [CourseController::class, 'archive']);
            Route::put('/{course}/status', [CourseController::class, 'updateStatus']);
        });
    });

    // Lesson routes
    // scopeBindings(): a {lesson} must belong to its {course}, a {resource} to its {lesson}
    Route::prefix('courses/{course}/lessons')->scopeBindings()->group(function () {
        Route::get('/', [LessonController::class, 'index']);
        Route::get('/{lesson}', [LessonController::class, 'show']);
        Route::middleware('role:administrator,teacher')->group(function () {
            Route::post('/', [LessonController::class, 'store']);
            Route::put('/reorder', [LessonController::class, 'reorder']);
            Route::put('/{lesson}', [LessonController::class, 'update']);
            Route::delete('/{lesson}', [LessonController::class, 'destroy']);
            Route::post('/{lesson}/publish', [LessonController::class, 'publish']);
            Route::post('/{lesson}/unpublish', [LessonController::class, 'unpublish']);
            Route::post('/{lesson}/archive', [LessonController::class, 'archive']);
            Route::put('/{lesson}/status', [LessonController::class, 'updateStatus']);
            Route::put('/{lesson}/availability', [LessonController::class, 'updateAvailability']);
            Route::post('/{lesson}/resources', [LessonController::class, 'addResource']);
            Route::delete('/{lesson}/resources/{resource}', [LessonController::class, 'deleteResource']);
            Route::put('/{lesson}/resources/reorder', [LessonController::class, 'reorderResources']);
        });
    });

    // Enrollment routes
    Route::prefix('enrollments')->group(function () {
        Route::middleware('role:administrator,teacher')->group(function () {
            Route::get('/', [EnrollmentController::class, 'index']);
            Route::get('/stats', [EnrollmentController::class, 'stats']);
            Route::get('/students', [EnrollmentController::class, 'students']);
            Route::get('/course/{course}', [EnrollmentController::class, 'courseEnrollments']);
            Route::get('/course/{course}/stats', [EnrollmentController::class, 'courseStats']);
            Route::put('/{enrollment}', [EnrollmentController::class, 'update']);
            Route::delete('/{enrollment}', [EnrollmentController::class, 'destroy']);
        });
        // Students may enroll themselves and read their own records (checked in the controller).
        Route::post('/', [EnrollmentController::class, 'store']);
        Route::get('/student/{user}', [EnrollmentController::class, 'studentEnrollments']);
        Route::get('/{enrollment}', [EnrollmentController::class, 'show']);
    });

    // Progress routes
    Route::prefix('progress')->group(function () {
        // Students may read their own progress (checked in the controller).
        Route::get('/student/{user}', [ProgressController::class, 'studentProgress']);
        Route::get('/enrollment/{enrollment}', [ProgressController::class, 'enrollmentProgress']);
        Route::get('/history/{user}', [ProgressController::class, 'learningHistory']);
        Route::middleware('role:administrator,teacher')->group(function () {
            Route::get('/students', [ProgressController::class, 'students']);
            Route::get('/course/{course}', [ProgressController::class, 'courseProgress']);
            Route::get('/course/{course}/summary', [ProgressController::class, 'courseProgressSummary']);
        });
    });

    // Lesson progress (authenticated user)
    Route::put('/lessons/{lesson}/progress', [ProgressController::class, 'toggleLessonProgress']);

    // Announcement routes
    Route::prefix('announcements')->group(function () {
        // Registered before /{announcement} so "history" is not treated as an id.
        Route::middleware('role:administrator,teacher')
            ->get('/history', [AnnouncementController::class, 'notificationHistory']);
        Route::get('/', [AnnouncementController::class, 'index']);
        Route::get('/{announcement}', [AnnouncementController::class, 'show']);
        Route::middleware('role:administrator,teacher')->group(function () {
            Route::post('/', [AnnouncementController::class, 'store']);
            Route::put('/{announcement}', [AnnouncementController::class, 'update']);
            Route::delete('/{announcement}', [AnnouncementController::class, 'destroy']);
            Route::post('/{announcement}/publish', [AnnouncementController::class, 'publish']);
            Route::post('/{announcement}/unpublish', [AnnouncementController::class, 'unpublish']);
        });
    });
});
