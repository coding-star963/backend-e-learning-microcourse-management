<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    /**
     * Reject requests from deactivated accounts, even if they still hold a token.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->isInactive()) {
            $user->tokens()->delete();

            return response()->json([
                'message' => 'Your account has been deactivated. Please contact an administrator.',
            ], 401);
        }

        return $next($request);
    }
}
