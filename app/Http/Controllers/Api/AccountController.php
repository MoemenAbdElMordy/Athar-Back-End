<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountController extends Controller
{
    use ApiResponse;

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        $user->is_active = false;
        $user->save();

        $user->tokens()->delete();

        return $this->successResponse([
            'deactivated' => true,
        ]);
    }
}
