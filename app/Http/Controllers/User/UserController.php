<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    /**
     * Display a listing of users.
     *
     * Only administrators can access this endpoint.
     * Supports optional filtering by role.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = User::query();

        if ($request->has('role') && in_array($request->role, ['administrator', 'teacher', 'student'])) {
            $query->where('role', $request->role);
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(15);

        return UserResource::collection($users);
    }

    /**
     * Store a newly created user.
     *
     * Only administrators can create users.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:administrator,teacher,student',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => $validated['role'],
            'is_active' => true,
        ]);

        return response()->json([
            'user' => new UserResource($user),
            'message' => 'User created successfully.',
        ], 201);
    }

    /**
     * Display the specified user.
     *
     * Only administrators can view user details.
     */
    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    /**
     * Update the specified user.
     *
     * Only administrators can update users.
     * Allows updating name, email, and role.
     * Password is not updated here for security.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|max:255|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:administrator,teacher,student',
        ]);

        $user->update($validated);

        return response()->json([
            'user' => new UserResource($user->fresh()),
            'message' => 'User updated successfully.',
        ]);
    }

    /**
     * Activate or deactivate a user.
     *
     * Only administrators can change user status.
     * Prevents deactivating the currently authenticated admin.
     */
    public function toggleStatus(User $user): JsonResponse
    {
        // Prevent an administrator from deactivating themselves
        if ($user->id === request()->user()->id) {
            return response()->json([
                'message' => 'You cannot deactivate your own account.',
            ], 422);
        }

        $user->update([
            'is_active' => !$user->is_active,
        ]);

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'user' => new UserResource($user->fresh()),
            'message' => "User {$status} successfully.",
        ]);
    }
}
