<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePlaceReportRequest;
use App\Http\Resources\AccessibilityContributionResource;
use App\Http\Resources\LocationResource;
use App\Http\Resources\ReviewResource;
use App\Models\AccessibilityContribution;
use App\Models\Location;
use App\Models\Review;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    use ApiResponse;

    public function nearby(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'min:0.1', 'max:100'],
            'search' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'government_id' => ['nullable', 'integer', 'exists:governments,id'],
        ]);

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $radiusKm = (float) ($validated['radius_km'] ?? 5);

        if (DB::connection()->getDriverName() === 'sqlite') {
            $locations = $this->nearbyForSqlite($validated, $lat, $lng, $radiusKm);

            return $this->successResponse(LocationResource::collection($locations));
        }

        $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))';

        $query = $this->baseLocationQuery()
            ->select('locations.*')
            ->selectRaw("{$distanceSql} as distance_km", [$lat, $lng, $lat])
            ->having('distance_km', '<=', $radiusKm)
            ->orderBy('distance_km');

        $this->applyLocationFilters($query, $validated);

        $locations = $query->limit(200)->get();

        return $this->successResponse(LocationResource::collection($locations));
    }

    private function nearbyForSqlite(array $validated, float $lat, float $lng, float $radiusKm)
    {
        $latDelta = $radiusKm / 111;
        $cosLat = cos(deg2rad($lat));
        $lngDelta = $radiusKm / max(111 * max($cosLat, 0.01), 0.0001);

        $query = $this->baseLocationQuery()
            ->select('locations.*')
            ->whereBetween('latitude', [$lat - $latDelta, $lat + $latDelta])
            ->whereBetween('longitude', [$lng - $lngDelta, $lng + $lngDelta]);

        $this->applyLocationFilters($query, $validated);

        return $query->limit(500)->get()
            ->map(function (Location $location) use ($lat, $lng): Location {
                $location->distance_km = $this->haversineDistanceKm($lat, $lng, (float) $location->latitude, (float) $location->longitude);

                return $location;
            })
            ->filter(fn (Location $location): bool => $location->distance_km <= $radiusKm)
            ->sortBy('distance_km')
            ->values()
            ->take(200);
    }

    private function haversineDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1Rad = deg2rad($lat1);
        $lat2Rad = deg2rad($lat2);
        $deltaLat = deg2rad($lat2 - $lat1);
        $deltaLng = deg2rad($lng2 - $lng1);

        $a = sin($deltaLat / 2) ** 2
            + cos($lat1Rad) * cos($lat2Rad) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return 6371 * $c;
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'government_id' => ['nullable', 'integer', 'exists:governments,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $this->baseLocationQuery()->orderByDesc('id');

        $this->applyLocationFilters($query, $validated);

        $locations = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();

        return $this->successResponse($this->paginatedData($locations, LocationResource::collection($locations->getCollection())));
    }

    public function show(int $id): JsonResponse
    {
        $location = $this->baseLocationQuery()
            ->with(['accessibilityReport'])
            ->find($id);

        if (!$location) {
            return $this->errorResponse('Location not found.', [], 404);
        }

        $ratings = $location->reviews()->with('user')->latest()->paginate(10);

        return $this->successResponse([
            'location' => new LocationResource($location),
            'ratings' => $this->paginatedData($ratings, ReviewResource::collection($ratings->getCollection())),
        ]);
    }

    public function storeReport(StorePlaceReportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $result = DB::transaction(function () use ($request, $data) {
            $location = Location::query()->create([
                'name' => $data['name'],
                'address' => $data['address'],
                'government_id' => $data['government_id'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'category_id' => $data['category_id'] ?? null,
            ]);

            $review = Review::query()->create([
                'user_id' => $request->user()->id,
                'location_id' => $location->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            $summary = Review::query()
                ->where('location_id', $location->id)
                ->selectRaw('COUNT(*) as count, AVG(rating) as avg_rating')
                ->first();

            $location->average_rating = round((float) ($summary?->avg_rating ?? 0), 2);
            $location->reviews_count = (int) ($summary?->count ?? 0);
            $location->save();

            $contribution = AccessibilityContribution::query()->create([
                'location_id' => $location->id,
                'user_id' => $request->user()->id,
                'wide_entrance' => (bool) ($data['wide_entrance'] ?? false),
                'wheelchair_accessible' => (bool) ($data['wheelchair_accessible'] ?? false),
                'elevator_available' => (bool) ($data['elevator_available'] ?? false),
                'ramp_available' => (bool) ($data['ramp_available'] ?? false),
                'parking' => (bool) ($data['parking'] ?? false),
                'accessible_toilet' => (bool) ($data['accessible_toilet'] ?? false),
                'notes' => $data['notes'] ?? null,
                'status' => 'pending',
                'verified_at' => null,
                'verified_by_admin_id' => null,
            ]);

            return [$location, $review, $contribution];
        });

        /** @var Location $location */
        [$location, $review, $contribution] = $result;

        $location->load(['category', 'accessibilityReport']);

        return $this->successResponse([
            'location' => new LocationResource($location),
            'rating' => new ReviewResource($review->load('user')),
            'accessibility_contribution' => new AccessibilityContributionResource($contribution),
        ], null, 201);
    }

    private function baseLocationQuery(): Builder
    {
        return Location::query()
            ->with(['category', 'accessibilityReport'])
            ->withCount(['reviews', 'accessibilityContributions'])
            ->withMax('accessibilityContributions as contributions_wide_entrance', 'wide_entrance')
            ->withMax('accessibilityContributions as contributions_wheelchair_accessible', 'wheelchair_accessible')
            ->withMax('accessibilityContributions as contributions_elevator_available', 'elevator_available')
            ->withMax('accessibilityContributions as contributions_ramp_available', 'ramp_available')
            ->withMax('accessibilityContributions as contributions_parking', 'parking')
            ->withMax('accessibilityContributions as contributions_accessible_toilet', 'accessible_toilet');
    }

    private function applyLocationFilters(Builder $query, array $validated): void
    {
        if (!empty($validated['search'])) {
            $search = (string) $validated['search'];
            $query->where(function (Builder $inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('address', 'like', "%{$search}%");
            });
        }

        if (!empty($validated['category_id'])) {
            $query->where('category_id', (int) $validated['category_id']);
        }

        if (!empty($validated['government_id'])) {
            $query->where('government_id', (int) $validated['government_id']);
        }
    }
}
