<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
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
            'email' => 'required|email',
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
                'email' => ['Your account has been deactivated. Please contact an administrator.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
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
     * Send a password reset link / token.
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $token = \Illuminate\Support\Str::random(60);

        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => \Illuminate\Support\Facades\Hash::make($token),
                'created_at' => now(),
            ]
        );

        \App\Models\User::where('email', $request->email)->firstOrFail()
            ->notify(new \App\Notifications\PasswordResetTokenNotification($token));

        // Opt-in for local demos without a real mailbox (RESET_TOKEN_IN_RESPONSE=true).
        // Never in production: returning the token would let anyone reset any account.
        if (config('app.reset_token_in_response') && ! app()->environment('production')) {
            return response()->json([
                'message' => 'Demo mode: use the reset token below to reset your password.',
                'dev_token' => $token,
            ]);
        }

        return response()->json([
            'message' => 'Password reset instructions have been sent to your email address.',
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

        $isExpired = \Illuminate\Support\Carbon::parse($record->created_at)->addMinutes(60)->isPast();
        if ($isExpired) {
            \Illuminate\Support\Facades\DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            throw ValidationException::withMessages([
                'token' => ['This password reset token has expired.'],
            ]);
        }

        if (!\Illuminate\Support\Facades\Hash::check($request->token, $record->token) && $request->token !== $record->token) {
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