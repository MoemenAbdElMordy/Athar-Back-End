<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLocationRatingRequest;
use App\Http\Resources\ReviewResource;
use App\Models\Location;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    use ApiResponse;

    public function index(Request $request, int $id): JsonResponse
    {
        $location = Location::query()->find($id);

        if (!$location) {
            return $this->errorResponse('Location not found.', [], 404);
        }

        $perPage = (int) $request->query('per_page', 15);
        $ratings = $location->reviews()->with('user')->latest()->paginate($perPage)->withQueryString();

        return $this->successResponse($this->paginatedData($ratings, ReviewResource::collection($ratings->getCollection())));
    }

    public function store(StoreLocationRatingRequest $request, int $id): JsonResponse
    {
        $location = Location::query()->find($id);

        if (!$location) {
            return $this->errorResponse('Location not found.', [], 404);
        }

        $rating = Review::query()->updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'location_id' => $location->id,
            ],
            [
                'rating' => $request->validated()['rating'],
                'comment' => $request->validated()['comment'] ?? null,
            ]
        );

        $this->refreshLocationRatingSummary($location->id);

        return $this->successResponse(new ReviewResource($rating->load('user')), null, 201);
    }

    private function refreshLocationRatingSummary(int $locationId): void
    {
        $summary = Review::query()
            ->where('location_id', $locationId)
            ->selectRaw('COUNT(*) as count, AVG(rating) as avg_rating')
            ->first();

        Location::query()
            ->where('id', $locationId)
            ->update([
                'reviews_count' => (int) ($summary?->count ?? 0),
                'average_rating' => round((float) ($summary?->avg_rating ?? 0), 2),
            ]);
    }
}
