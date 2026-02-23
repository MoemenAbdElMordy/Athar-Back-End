<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => (object) [],
            ], 401);
        }

        if (empty($roles) || !in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden for this role.',
                'errors' => (object) [],
            ], 403);
        }

        if ($user->role === 'volunteer' && !$user->role_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Volunteer account is pending approval.',
                'errors' => (object) [],
            ], 403);
        }

        return $next($request);
    }
}
