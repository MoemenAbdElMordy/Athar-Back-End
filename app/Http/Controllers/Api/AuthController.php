<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $name = $data['name'] ?? $data['full_name'] ?? 'User';

        $user = User::create([
            'name' => $name,
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'disability_type' => $data['disability_type'] ?? null,
            'mobility_aids' => $data['mobility_aids'] ?? null,
            'role' => 'user',
            'is_active' => true,
        ]);

        $tokenName = $this->tokenName($request);
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (!$user->is_active) {
            return response()->json(['message' => 'Account is inactive.'], 403);
        }

        $tokenName = $this->tokenName($request);
        $token = $user->createToken($tokenName)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return response()->json(['success' => true]);
    }

    private function tokenName(Request $request): string
    {
        $userAgent = (string) $request->userAgent();

        if (trim($userAgent) === '') {
            return 'mobile';
        }

        return mb_substr($userAgent, 0, 120);
    }
}
