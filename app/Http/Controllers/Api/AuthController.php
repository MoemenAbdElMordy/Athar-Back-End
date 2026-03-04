<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ChangePasswordRequest;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Requests\Api\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

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
            'password_changed_at' => now(),
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'disability_type' => $data['disability_type'] ?? null,
            'assistance_needs' => $data['assistance_needs'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
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
        $certificationDocumentPath = ($request->file('certification_document') ?? $request->file('certification'))
            ? ($request->file('certification_document') ?? $request->file('certification'))->store('volunteer-certifications', 'public')
            : null;

        $user = User::create([
            'name' => $name,
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'],
            'password' => $data['password'],
            'password_changed_at' => now(),
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'national_id' => $data['national_id'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'id_document_path' => $idDocumentPath,
            'certification_document_path' => $certificationDocumentPath,
            'volunteer_languages' => $data['volunteer_languages'] ?? null,
            'volunteer_availability' => $data['volunteer_availability'] ?? null,
            'volunteer_motivation' => $data['volunteer_motivation'] ?? null,
            'disability_type' => $data['disability_type'] ?? null,
            'mobility_aids' => $data['mobility_aids'] ?? null,
            'assistance_needs' => $data['assistance_needs'] ?? null,
            'emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            'emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
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

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        if (!Hash::check($validated['current_password'], (string) $user->password)) {
            return $this->errorResponse('Current password is incorrect.', [], 422);
        }

        $user->password = $validated['new_password'];
        $user->password_changed_at = now();
        $user->save();

        return $this->successResponse([
            'changed' => true,
        ]);
    }

    public function sessions(Request $request): JsonResponse
    {
        $currentToken = $request->user()?->currentAccessToken();

        $tokens = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $request->user()->id)
            ->latest('id')
            ->get()
            ->map(function (PersonalAccessToken $token) use ($currentToken): array {
                return [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $token->abilities ?? [],
                    'last_used_at' => optional($token->last_used_at)->toIso8601String(),
                    'created_at' => optional($token->created_at)->toIso8601String(),
                    'is_current' => $currentToken ? ((int) $token->id === (int) $currentToken->id) : false,
                ];
            })
            ->values();

        return $this->successResponse($tokens);
    }

    public function destroySession(Request $request, int $id): JsonResponse
    {
        $token = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $request->user()->id)
            ->find($id);

        if (!$token) {
            return $this->errorResponse('Session not found.', [], 404);
        }

        $token->delete();

        return $this->successResponse([
            'deleted' => true,
        ]);
    }

    public function destroyOtherSessions(Request $request): JsonResponse
    {
        $currentTokenId = $request->user()?->currentAccessToken()?->id;

        $query = PersonalAccessToken::query()
            ->where('tokenable_type', User::class)
            ->where('tokenable_id', $request->user()->id);

        if ($currentTokenId) {
            $query->where('id', '!=', $currentTokenId);
        }

        $deleted = $query->delete();

        return $this->successResponse([
            'deleted_count' => $deleted,
        ]);
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
