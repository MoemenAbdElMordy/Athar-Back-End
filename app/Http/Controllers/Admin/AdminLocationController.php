<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreLocationRequest;
use App\Http\Requests\Admin\UpdateLocationRequest;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AdminLocationController extends Controller
{
    public function show(int $id): JsonResponse
    {
        $location = Location::query()
            ->with(['category', 'government', 'accessibilityReport'])
            ->find($id);

        if (!$location) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        return response()->json($location);
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
                'admin_locations:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );

            $cachedPayload = Cache::get($cacheKey);
            if (is_array($cachedPayload)) {
                return response()->json($cachedPayload);
            }
        }

        $query = Location::query()
            ->with(['category:id,name', 'government:id,accessible_locations', 'accessibilityReport']);

        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'verified' => ['nullable', 'boolean'],
        ]);

        if ($request->filled('search')) {
            $search = (string) $request->query('search');
            $query->where(function ($inner) use ($search) {
                $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if ($request->filled('government_id')) {
            $query->where('government_id', $request->query('government_id'));
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->query('category_id'));
        }

        if ($request->filled('verified')) {
            $verified = filter_var($request->query('verified'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($verified !== null) {
                $query->whereHas('accessibilityReport', function ($reportQuery) use ($verified) {
                    $reportQuery->where('verified', $verified);
                });
            }
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $locations = $query->orderByDesc('id')->paginate($perPage);
        $payload = $locations->toArray();

        if ($cacheSeconds > 0) {
            $adminId = Auth::guard('web')->id();
            $cacheKey = sprintf(
                'admin_locations:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );
            Cache::put($cacheKey, $payload, now()->addSeconds($cacheSeconds));
        }

        return response()->json($payload);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $location = Location::create($request->validated());
        $this->bumpCacheVersion();

        return response()->json(
            $location->load(['category', 'government', 'accessibilityReport']),
            201,
        );
    }

    public function update(UpdateLocationRequest $request, int $id): JsonResponse
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $location->update($request->validated());
        $this->bumpCacheVersion();

        return response()->json($location->load(['category', 'government', 'accessibilityReport']));
    }

    public function destroy(int $id): JsonResponse
    {
        $location = Location::find($id);

        if (!$location) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $location->delete();
        $this->bumpCacheVersion();

        return response()->json(['success' => true]);
    }

    private function cacheVersion(): int
    {
        return max((int) Cache::get('admin_locations:version', 1), 1);
    }

    private function bumpCacheVersion(): void
    {
        Cache::forever('admin_locations:version', $this->cacheVersion() + 1);
    }
}
