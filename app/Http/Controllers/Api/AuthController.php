<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function registerUser(RegisterRequest $request): JsonResponse
    {
        $request->merge(['role' => 'user']);

        $data = $request->validated();

        $name = $data['name'] ?? $data['full_name'] ?? 'User';

        $user = User::create([
            'name' => $name,
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'role' => 'user',
            'role_locked' => false,
            'role_verified_at' => now(),
            'is_active' => true,
        ]);

        $tokenName = $this->tokenName($request);
        $token = $user->createToken($tokenName)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], null, 201);
    }

    public function registerVolunteer(RegisterRequest $request): JsonResponse
    {
        $request->merge(['role' => 'volunteer']);

        return $this->register($request);
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $name = $data['name'] ?? $data['full_name'] ?? 'User';
        $role = $data['role'] ?? 'user';
        $idDocumentPath = $request->hasFile('id_document')
            ? $request->file('id_document')->store('volunteer-documents', 'public')
            : null;

        $user = User::create([
            'name' => $name,
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'id_document_path' => $idDocumentPath,
            'volunteer_languages' => $data['volunteer_languages'] ?? null,
            'volunteer_availability' => $data['volunteer_availability'] ?? null,
            'volunteer_motivation' => $data['volunteer_motivation'] ?? null,
            'disability_type' => $data['disability_type'] ?? null,
            'mobility_aids' => $data['mobility_aids'] ?? null,
            'role' => $role,
            'role_locked' => false,
            'role_verified_at' => $role === 'volunteer' ? null : now(),
            'is_active' => true,
        ]);

        $tokenName = $this->tokenName($request);
        $token = $user->createToken($tokenName)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ], null, 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['password'], $user->password)) {
            return $this->errorResponse('Invalid credentials.', [], 401);
        }

        if (!$user->is_active) {
            return $this->errorResponse('Account is inactive.', [], 403);
        }

        $tokenName = $this->tokenName($request);
        $token = $user->createToken($tokenName)->plainTextToken;

        return $this->successResponse([
            'token' => $token,
            'user' => new UserResource($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(new UserResource($request->user()));
    }

    public function logout(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        if ($token) {
            $token->delete();
        }

        return $this->successResponse(null);
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
