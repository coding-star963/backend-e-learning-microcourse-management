<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherController extends Controller
{
    /**
     * List teachers with their assigned course counts.
     *
     * Used by Teacher Management and the course teacher selector.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::where('role', User::ROLE_TEACHER)->withCount('courses');

        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $teachers = $query->orderBy('name')->get()->map(fn (User $teacher) => [
            'id' => $teacher->id,
            'name' => $teacher->name,
            'email' => $teacher->email,
            'is_active' => $teacher->is_active,
            'profile_photo' => $teacher->profile_photo_url,
            'courses_count' => $teacher->courses_count,
            'created_at' => $teacher->created_at,
        ]);

        return response()->json(['data' => $teachers]);
    }
}
