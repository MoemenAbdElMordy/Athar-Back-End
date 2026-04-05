<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovePlaceSubmissionRequest;
use App\Http\Requests\Admin\RejectPlaceSubmissionRequest;
use App\Models\Location;
use App\Models\PlaceSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AdminPlaceSubmissionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cacheSeconds = max((int) config('athar.admin_list_cache_seconds', 60), 0);
        $refreshRequested = filter_var($request->query('refresh', false), FILTER_VALIDATE_BOOL);
        $queryParams = $request->query();
        unset($queryParams['refresh']);

        if ($cacheSeconds > 0 && !$refreshRequested) {
            $adminId = Auth::guard('web')->id();
            $cacheKey = sprintf(
                'admin_place_submissions:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );

            $cachedPayload = Cache::get($cacheKey);
            if (is_array($cachedPayload)) {
                return response()->json($cachedPayload);
            }
        }

        $query = PlaceSubmission::query()->with([
            'submitter:id,name,full_name,email',
            'category:id,name',
            'reviewer:id,name,full_name,email',
        ]);

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        if ($request->filled('status')) {
            $query->where('status', (string) $request->query('status'));
        }

        if ($request->filled('search')) {
            $search = (string) $validated['search'];
            $query->where(function ($inner) use ($search) {
                $inner
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($validated['per_page'] ?? 15);
        $submissions = $query->orderByDesc('id')->paginate($perPage);
        $payload = [
            ...$submissions->toArray(),
            'summary' => [
                'pending' => PlaceSubmission::query()->where('status', 'pending')->count(),
                'approved' => PlaceSubmission::query()->where('status', 'approved')->count(),
                'rejected' => PlaceSubmission::query()->where('status', 'rejected')->count(),
            ],
        ];

        if ($cacheSeconds > 0) {
            $adminId = Auth::guard('web')->id();
            $cacheKey = sprintf(
                'admin_place_submissions:index:%d:%d:%s',
                (int) $adminId,
                $this->cacheVersion(),
                sha1(http_build_query($queryParams)),
            );
            Cache::put($cacheKey, $payload, now()->addSeconds($cacheSeconds));
        }

        return response()->json($payload);
    }

    public function approve(ApprovePlaceSubmissionRequest $request, int $id): JsonResponse
    {
        $submission = PlaceSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($submission->status !== 'pending') {
            return response()->json(['message' => 'Only pending submissions can be approved.'], 422);
        }

        $admin = Auth::guard('web')->user();

        $submission->status = 'approved';
        $submission->reviewed_by_admin_id = $admin?->id;
        $submission->reviewed_at = now();
        $submission->rejection_reason = null;
        $submission->save();

        $createdLocation = null;

        $validated = $request->validated();
        $createLocation = (bool) ($validated['create_location'] ?? false);

        if ($createLocation) {
            $createdLocation = Location::create([
                'name' => $submission->name,
                'address' => $submission->address,
                'government_id' => $validated['government_id'],
                'latitude' => $submission->lat,
                'longitude' => $submission->lng,
                'category_id' => $submission->category_id,
            ]);

            Cache::forever('admin_locations:version', max((int) Cache::get('admin_locations:version', 1), 1) + 1);
        }

        $this->bumpCacheVersion();

        return response()->json([
            'success' => true,
            'submission' => $submission,
            'location' => $createdLocation,
        ]);
    }

    public function reject(RejectPlaceSubmissionRequest $request, int $id): JsonResponse
    {
        $submission = PlaceSubmission::find($id);

        if (!$submission) {
            return response()->json(['message' => 'Not Found.'], 404);
        }

        if ($submission->status !== 'pending') {
            return response()->json(['message' => 'Only pending submissions can be rejected.'], 422);
        }

        $admin = Auth::guard('web')->user();

        $submission->status = 'rejected';
        $submission->reviewed_by_admin_id = $admin?->id;
        $submission->reviewed_at = now();
        $submission->rejection_reason = $request->validated()['rejection_reason'];
        $submission->save();

        $this->bumpCacheVersion();

        return response()->json([
            'success' => true,
            'submission' => $submission,
        ]);
    }

    private function cacheVersion(): int
    {
        return max((int) Cache::get('admin_place_submissions:version', 1), 1);
    }

    private function bumpCacheVersion(): void
    {
        Cache::forever('admin_place_submissions:version', $this->cacheVersion() + 1);
    }
}
