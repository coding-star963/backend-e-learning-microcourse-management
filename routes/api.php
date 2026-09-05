<?php

use App\Http\Controllers\Announcement\AnnouncementController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Category\CategoryController;
use App\Http\Controllers\Course\CourseController;
use App\Http\Controllers\Enrollment\EnrollmentController;
use App\Http\Controllers\Lesson\LessonController;
use App\Http\Controllers\Progress\ProgressController;
use App\Http\Controllers\Profile\ProfileController;
use App\Http\Controllers\User\UserController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::post('/login', [AuthController::class, 'login']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);

    // User management routes (administrator only)
    Route::middleware('role:administrator')->prefix('users')->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::get('/{user}', [UserController::class, 'show']);
        Route::put('/{user}', [UserController::class, 'update']);
        Route::post('/{user}/toggle-status', [UserController::class, 'toggleStatus']);
    });

    // Profile routes
    Route::prefix('profile')->group(function () {
        Route::get('/', [ProfileController::class, 'show']);
        Route::put('/', [ProfileController::class, 'update']);
        Route::put('/password', [ProfileController::class, 'updatePassword']);
        Route::post('/photo', [ProfileController::class, 'updatePhoto']);
    });

    // Category routes (administrator and teacher)
    Route::middleware('role:administrator,teacher')->prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('/{category}', [CategoryController::class, 'show']);
        Route::put('/{category}', [CategoryController::class, 'update']);
        Route::delete('/{category}', [CategoryController::class, 'destroy']);
    });

    // Course routes (administrator and teacher)
    Route::middleware('role:administrator,teacher')->prefix('courses')->group(function () {
        Route::get('/', [CourseController::class, 'index']);
        Route::post('/', [CourseController::class, 'store']);
        Route::get('/categories', [CourseController::class, 'categories']);
        Route::get('/{course}', [CourseController::class, 'show']);
        Route::put('/{course}', [CourseController::class, 'update']);
        Route::delete('/{course}', [CourseController::class, 'destroy']);
        Route::post('/{course}/publish', [CourseController::class, 'publish']);
        Route::post('/{course}/unpublish', [CourseController::class, 'unpublish']);
        Route::post('/{course}/archive', [CourseController::class, 'archive']);
        Route::put('/{course}/status', [CourseController::class, 'updateStatus']);
    });

    // Lesson routes (administrator and teacher)
    Route::middleware('role:administrator,teacher')->prefix('courses/{course}/lessons')->group(function () {
        Route::get('/', [LessonController::class, 'index']);
        Route::post('/', [LessonController::class, 'store']);
        Route::get('/{lesson}', [LessonController::class, 'show']);
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
        Route::put('/reorder', [LessonController::class, 'reorder']);
    });

    // Enrollment routes (administrator and teacher)
    Route::middleware('role:administrator,teacher')->prefix('enrollments')->group(function () {
        Route::get('/', [EnrollmentController::class, 'index']);
        Route::post('/', [EnrollmentController::class, 'store']);
        Route::get('/stats', [EnrollmentController::class, 'stats']);
        Route::get('/{enrollment}', [EnrollmentController::class, 'show']);
        Route::put('/{enrollment}', [EnrollmentController::class, 'update']);
        Route::delete('/{enrollment}', [EnrollmentController::class, 'destroy']);
        Route::get('/course/{course}', [EnrollmentController::class, 'courseEnrollments']);
        Route::get('/course/{course}/stats', [EnrollmentController::class, 'courseStats']);
        Route::get('/student/{user}', [EnrollmentController::class, 'studentEnrollments']);
    });

    // Progress routes (administrator and teacher)
    Route::middleware('role:administrator,teacher')->prefix('progress')->group(function () {
        Route::get('/student/{user}', [ProgressController::class, 'studentProgress']);
        Route::get('/course/{course}', [ProgressController::class, 'courseProgress']);
        Route::get('/enrollment/{enrollment}', [ProgressController::class, 'enrollmentProgress']);
        Route::get('/history/{user}', [ProgressController::class, 'learningHistory']);
        Route::get('/course/{course}/summary', [ProgressController::class, 'courseProgressSummary']);
    });

    // Lesson progress (authenticated user)
    Route::put('/lessons/{lesson}/progress', [ProgressController::class, 'toggleLessonProgress']);

    // Announcement routes (administrator and teacher)
    Route::middleware('role:administrator,teacher')->prefix('announcements')->group(function () {
        Route::get('/', [AnnouncementController::class, 'index']);
        Route::post('/', [AnnouncementController::class, 'store']);
        Route::get('/history', [AnnouncementController::class, 'notificationHistory']);
        Route::get('/{announcement}', [AnnouncementController::class, 'show']);
        Route::put('/{announcement}', [AnnouncementController::class, 'update']);
        Route::delete('/{announcement}', [AnnouncementController::class, 'destroy']);
        Route::post('/{announcement}/publish', [AnnouncementController::class, 'publish']);
        Route::post('/{announcement}/unpublish', [AnnouncementController::class, 'unpublish']);
    });
});
