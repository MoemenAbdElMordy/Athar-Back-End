<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminAccountController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:user,volunteer'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $user = User::query()->create([
            'name' => $data['name'],
            'full_name' => $data['full_name'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'role_locked' => false,
            'role_verified_at' => null,
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'user' => $user,
        ], 201);
    }

    public function index(): JsonResponse
    {
        $pendingVolunteerCount = User::query()
            ->where('role', 'volunteer')
            ->whereNull('role_verified_at')
            ->count();

        $volunteerCount = User::query()
            ->where('role', 'volunteer')
            ->whereNotNull('role_verified_at')
            ->count();

        $userCount = User::query()
            ->where('role', 'user')
            ->count();

        $baseSelect = [
            'id',
            'name',
            'full_name',
            'email',
            'phone',
            'role',
            'role_locked',
            'role_verified_at',
            'is_active',
            'created_at',
        ];

        $pendingVolunteerRequests = User::query()
            ->select($baseSelect)
            ->where('role', 'volunteer')
            ->whereNull('role_verified_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $volunteerAccounts = User::query()
            ->select($baseSelect)
            ->where('role', 'volunteer')
            ->whereNotNull('role_verified_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $userAccounts = User::query()
            ->select($baseSelect)
            ->where('role', 'user')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        return response()->json([
            'counts' => [
                'users' => $userCount,
                'volunteers' => $volunteerCount,
                'pending_volunteer_requests' => $pendingVolunteerCount,
            ],
            'pending_volunteer_requests' => $pendingVolunteerRequests,
            'volunteer_accounts' => $volunteerAccounts,
            'user_accounts' => $userAccounts,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (!$user) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Admin account updates are not allowed here.'], 422);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['sometimes', 'required', 'in:user,volunteer'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }

        if (array_key_exists('full_name', $data)) {
            $user->full_name = $data['full_name'];
        }

        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }

        if (array_key_exists('phone', $data)) {
            $user->phone = $data['phone'];
        }

        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        if (array_key_exists('is_active', $data)) {
            $user->is_active = (bool) $data['is_active'];
        }

        if (array_key_exists('role', $data)) {
            $newRole = $data['role'];

            if ($newRole === 'user') {
                $user->role = 'user';
                $user->role_verified_at = null;
                $user->role_locked = false;
            }

            if ($newRole === 'volunteer' && $user->role !== 'volunteer') {
                $user->role = 'volunteer';
                $user->role_verified_at = null;
                $user->role_locked = false;
            }
        }

        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (!$user) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($user->role === 'admin') {
            return response()->json(['message' => 'Admin account deletion is not allowed.'], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function approveVolunteer(int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (!$user) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($user->role !== 'volunteer') {
            return response()->json(['message' => 'Only volunteer requests can be approved.'], 422);
        }

        $user->role_verified_at = now();
        $user->role_locked = true;
        $user->is_active = true;
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    public function rejectVolunteer(int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (!$user) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($user->role !== 'volunteer') {
            return response()->json(['message' => 'Only volunteer requests can be rejected.'], 422);
        }

        $user->role = 'user';
        $user->role_verified_at = null;
        $user->role_locked = false;
        $user->save();

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }
}
