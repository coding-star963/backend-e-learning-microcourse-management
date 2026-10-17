<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Rules\GmailAddress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Handle a login request.
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email', new GmailAddress],
            'password' => 'required',
        ]);

        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        $user = Auth::user();

        if (!$user) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials do not match our records.'],
            ]);
        }

        if (!$user->isActive()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [User::DEACTIVATED_MESSAGE],
            ]);
        }

        if ($user->isSuspended()) {
            Auth::logout();
            throw ValidationException::withMessages([
                'email' => [User::SUSPENDED_MESSAGE],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    /**
     * Student self-registration (mobile app). Always creates a student
     * account with a Gmail address, then signs it in like login().
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', 'max:255', 'unique:users,email', new GmailAddress],
            'password' => 'required|string|min:8|confirmed',
        ], [
            'email.unique' => 'An account with this email already exists.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'password' => $validated['password'],
            'role' => User::ROLE_STUDENT,
            'is_active' => true,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
            'message' => 'Account created successfully.',
        ], 201);
    }

    /**
     * Handle a logout request.
     */
    public function logout(Request $request): JsonResponse
    {
        // Only sign out this device; the user's other sessions (web/mobile) stay active.
        $token = $request->user()->currentAccessToken();
        if ($token instanceof \Laravel\Sanctum\PersonalAccessToken) {
            $token->delete();
        }

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Password recovery, step 1: confirm the email belongs to an account.
     *
     * No email or OTP is used. When the email exists, a short-lived reset token
     * is returned so the app can go straight to the "new password" step.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ], [
            'email.exists' => 'No account found with that email address.',
        ]);

        $token = \Illuminate\Support\Str::random(60);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => \Illuminate\Support\Facades\Hash::make($token),
                'created_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Email verified. You can now set a new password.',
            'reset_token' => $token,
        ]);
    }

    /**
     * Reset the user password using a token.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $record = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        if (!$record) {
            throw ValidationException::withMessages([
                'email' => ['Invalid password reset token.'],
            ]);
        }

        $isExpired = \Illuminate\Support\Carbon::parse($record->created_at)->addMinutes(15)->isPast();
        if ($isExpired) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'token' => ['This reset request has expired. Please enter your email again.'],
            ]);
        }

        if (! \Illuminate\Support\Facades\Hash::check($request->token, $record->token)) {
            throw ValidationException::withMessages([
                'token' => ['The provided password reset token is invalid.'],
            ]);
        }

        $user = \App\Models\User::where('email', $request->email)->firstOrFail();
        $user->update([
            'password' => $request->password,
        ]);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
        $user->tokens()->delete();

        return response()->json([
            'message' => 'Your password has been reset successfully.',
        ]);
    }
}