<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonAccept
{
    public function handle(Request $request, Closure $next): Response
    {
        $acceptHeader = (string) $request->header('Accept', '');

        if (!str_contains(mb_strtolower($acceptHeader), 'application/json')) {
            return response()->json([
                'success' => false,
                'message' => 'Please set Accept: application/json.',
                'errors' => [
                    'accept' => ['The request must accept application/json.'],
                ],
            ], 406);
        }

        return $next($request);
    }
}
