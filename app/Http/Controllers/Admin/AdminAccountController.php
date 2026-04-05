<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\VolunteerReviewItemResource;
use App\Models\HelpRequest;
use App\Models\User;
use App\Models\VolunteerReview;
use App\Services\VolunteerAnalyticsService;
use App\Services\VolunteerEarningsService;
use App\Services\VolunteerPerformanceService;
use App\Services\VolunteerReviewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

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

        $this->bumpCacheVersion();

        return response()->json([
            'success' => true,
            'user' => $user,
        ], 201);
    }

    public function index(Request $request): JsonResponse
    {
        $cacheSeconds = max((int) config('athar.admin_list_cache_seconds', 60), 0);
        $refreshRequested = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOL);
        $queryParams = $request->query();
        unset($queryParams['refresh']);

        if ($cacheSeconds > 0 && !$refreshRequested) {
            $adminId = Auth::guard('web')->id();
            $cacheKey = sprintf(
                'admin_accounts:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );

            $cachedPayload = Cache::get($cacheKey);
            if (is_array($cachedPayload)) {
                return response()->json($cachedPayload);
            }
        }

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

        $pendingVolunteerSelect = [
            ...$baseSelect,
            'city',
            'national_id',
            'date_of_birth',
            'volunteer_languages',
            'volunteer_availability',
            'volunteer_motivation',
            'id_document_path',
            'certification_document_path',
        ];

        $pendingVolunteerRequests = User::query()
            ->select($pendingVolunteerSelect)
            ->where('role', 'volunteer')
            ->whereNull('role_verified_at')
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        $hasReviewsTable = Schema::hasTable('volunteer_reviews');
        $hasFeeColumns = Schema::hasColumn('help_requests', 'net_amount_cents');
        $hasServiceFee = Schema::hasColumn('help_requests', 'service_fee');

        $volunteerAccounts = User::query()
            ->select($baseSelect)
            ->where('role', 'volunteer')
            ->whereNotNull('role_verified_at')
            ->withCount(['helpRequestsAccepted as completed_count' => function ($q) {
                $q->where('status', 'completed');
            }])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($v) use ($hasReviewsTable, $hasFeeColumns, $hasServiceFee) {
                $avgRating = $hasReviewsTable
                    ? VolunteerReview::query()->where('volunteer_id', $v->id)->avg('rating')
                    : null;

                $totalEarnings = 0;
                if ($hasFeeColumns) {
                    $totalEarnings = (int) HelpRequest::query()->where('volunteer_id', $v->id)->where('status', 'completed')->sum('net_amount_cents');
                } elseif ($hasServiceFee) {
                    $totalEarnings = (int) HelpRequest::query()->where('volunteer_id', $v->id)->where('status', 'completed')->sum('service_fee');
                }

                $v->completed_requests = (int) $v->completed_count;
                $v->avg_rating = round((float) $avgRating, 1);
                $v->total_earnings = round($totalEarnings / 100, 2);

                return $v;
            });

        $userAccounts = User::query()
            ->select($baseSelect)
            ->where('role', 'user')
            ->withCount(['helpRequestsMade as requests_count'])
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($u) {
                $u->total_requests = (int) $u->requests_count;
                return $u;
            });

        $payload = [
            'counts' => [
                'users' => $userCount,
                'volunteers' => $volunteerCount,
                'pending_volunteer_requests' => $pendingVolunteerCount,
            ],
            'pending_volunteer_requests' => $pendingVolunteerRequests,
            'volunteer_accounts' => $volunteerAccounts,
            'user_accounts' => $userAccounts,
        ];

        if ($cacheSeconds > 0) {
            $adminId = Auth::guard('web')->id();
            $cacheKey = sprintf(
                'admin_accounts:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );
            Cache::put($cacheKey, $payload, now()->addSeconds($cacheSeconds));
        }

        return response()->json($payload);
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
        $this->bumpCacheVersion();

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
        $this->bumpCacheVersion();

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
        $this->bumpCacheVersion();

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
        $this->bumpCacheVersion();

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    public function volunteerAnalytics(
        Request $request,
        int $id,
        VolunteerAnalyticsService $analyticsService,
        VolunteerEarningsService $earningsService,
        VolunteerPerformanceService $performanceService,
        VolunteerReviewsService $reviewsService,
    ): JsonResponse {
        $user = User::query()
            ->select(['id', 'name', 'full_name', 'email', 'phone', 'role', 'is_active', 'role_verified_at', 'created_at'])
            ->find($id);

        if (!$user) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($user->role !== 'volunteer') {
            return response()->json(['message' => 'Analytics are only available for volunteer accounts.'], 422);
        }

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $ratingFilter = isset($validated['rating']) ? (int) $validated['rating'] : null;
        $reviewsPaginator = $reviewsService->paginated($user->id, $perPage, $ratingFilter);

        return response()->json([
            'volunteer' => $user,
            'impact' => $analyticsService->overview($user->id),
            'earnings' => $earningsService->earnings($user->id),
            'performance' => $performanceService->performance($user->id),
            'reviews' => [
                'summary' => $reviewsService->summary($user->id),
                'data' => VolunteerReviewItemResource::collection(collect($reviewsPaginator->items()))->resolve(),
                'meta' => [
                    'current_page' => $reviewsPaginator->currentPage(),
                    'per_page' => $reviewsPaginator->perPage(),
                    'total' => $reviewsPaginator->total(),
                ],
            ],
        ]);
    }

    private function cacheVersion(): int
    {
        return max((int) Cache::get('admin_accounts:version', 1), 1);
    }

    private function bumpCacheVersion(): void
    {
        Cache::forever('admin_accounts:version', $this->cacheVersion() + 1);
    }
}
