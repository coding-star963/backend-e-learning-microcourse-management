<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject requests from deactivated or suspended accounts, even if they still hold a token.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! in_array($user->role, [User::ROLE_ADMINISTRATOR, User::ROLE_STUDENT], true)) {
            return response()->json(['message' => 'This account role is no longer supported. Contact an administrator.'], 403);
        }

        if ($user && $user->isInactive()) {
            $user->tokens()->delete();

            return response()->json([
                'message' => User::DEACTIVATED_MESSAGE,
                'account_status' => User::ACCOUNT_DEACTIVATED,
            ], 401);
        }

        // Every request keeps answering with the suspension notice (the token is kept)
        // so the apps can tell the user why they were signed out. Logging out still works.
        if ($user && $user->isSuspended() && ! $request->is('api/logout')) {
            return response()->json([
                'message' => User::SUSPENDED_MESSAGE,
                'account_status' => User::ACCOUNT_SUSPENDED,
            ], 403);
        }

        return $next($request);
    }
}
