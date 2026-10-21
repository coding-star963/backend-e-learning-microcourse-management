<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Notifications\StudentNotice;
use App\Rules\GmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

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

        if ($request->has('role') && in_array($request->role, ['administrator', 'student'])) {
            $query->where('role', $request->role);
        }

        if (in_array($request->status, [User::ACCOUNT_ACTIVE, User::ACCOUNT_SUSPENDED, User::ACCOUNT_DEACTIVATED], true)) {
            $query->withAccountStatus($request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) ($request->per_page ?? 15), 100);
        $users = $query->orderBy('created_at', 'desc')->paginate($perPage > 0 ? $perPage : 15);

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
            // Every account signs in with a Gmail address.
            'email' => ['required', 'email', 'max:255', 'unique:users,email', new GmailAddress],
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:administrator,student',
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
        // A deactivated account must be activated before it can be edited.
        if ($user->isInactive()) {
            return response()->json(['message' => User::FROZEN_MESSAGE], 422);
        }

        // A changed email must be a Gmail address (only Gmail can sign in).
        $changesEmail = $request->filled('email')
            && strcasecmp(trim((string) $request->email), $user->email) !== 0;

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes', 'email', 'max:255', 'unique:users,email,' . $user->id,
                Rule::when($changesEmail, [new GmailAddress]),
            ],
            'role' => 'sometimes|in:administrator,student',
        ]);

        // An administrator demoting themself would lock them out of user management.
        if (isset($validated['role']) && $validated['role'] !== $user->role && $user->id === $request->user()->id) {
            return response()->json([
                'message' => 'You cannot change your own role.',
            ], 422);
        }

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

        // Activating or deactivating also ends a suspension, so the account has one clear status.
        $user->update([
            'is_active' => !$user->is_active,
            'suspended_at' => null,
        ]);

        // Sign a deactivated user out of the web and mobile apps immediately.
        if (! $user->is_active) {
            $user->tokens()->delete();
        } elseif ($user->isStudent()) {
            // Updates resume: catch up on course changes skipped while deactivated.
            $user->refreshEnrollmentProgress();
        }

        $status = $user->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'user' => new UserResource($user->fresh()),
            'message' => "User {$status} successfully.",
        ]);
    }

    /**
     * Suspend an account: it cannot sign in to the web or mobile app until it is reactivated.
     *
     * Administrators may suspend any other account.
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $this->authorizeSuspension($request->user(), $user);

        if ($user->isInactive()) {
            return response()->json([
                'message' => 'This account is deactivated. Activate it before changing its suspension.',
            ], 422);
        }

        if (! $user->isSuspended()) {
            $user->update(['suspended_at' => now()]);
        }

        return response()->json([
            'user' => new UserResource($user->fresh()),
            'message' => "{$user->name}'s account was suspended. They are signed out and cannot sign in until it is reactivated.",
        ]);
    }

    /**
     * Lift a suspension so the account can sign in again.
     */
    public function unsuspend(Request $request, User $user): JsonResponse
    {
        $this->authorizeSuspension($request->user(), $user);

        if ($user->isSuspended()) {
            $user->update(['suspended_at' => null]);

            if ($user->isStudent()) {
                $user->notify(new StudentNotice(
                    'account_reactivated',
                    'Account reactivated',
                    'Your account was reactivated. Welcome back — you can continue learning.',
                    null,
                    $request->user(),
                ));
            }
        }

        return response()->json([
            'user' => new UserResource($user->fresh()),
            'message' => "{$user->name}'s account was reactivated.",
        ]);
    }

    private function authorizeSuspension(User $actor, User $user): void
    {
        abort_if($actor->id === $user->id, 422, 'You cannot suspend or reactivate your own account.');

        abort_unless($actor->isAdministrator(), 403, 'Only administrators can manage accounts.');
    }
}
