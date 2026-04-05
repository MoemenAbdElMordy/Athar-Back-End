<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\VolunteerAnalyticsEarningsRequest;
use App\Http\Requests\Api\VolunteerAnalyticsReviewsRequest;
use App\Http\Resources\VolunteerEarningsResource;
use App\Http\Resources\VolunteerPerformanceResource;
use App\Http\Resources\VolunteerReviewItemResource;
use App\Services\VolunteerEarningsService;
use App\Services\VolunteerPerformanceService;
use App\Services\VolunteerReviewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VolunteerAnalyticsController extends Controller
{
    use ApiResponse;

    public function __construct(
        private readonly VolunteerEarningsService $earningsService,
        private readonly VolunteerPerformanceService $performanceService,
        private readonly VolunteerReviewsService $reviewsService,
    ) {}

    public function earnings(VolunteerAnalyticsEarningsRequest $request): JsonResponse
    {
        $months = (int) ($request->validated()['months'] ?? 6);
        $data = $this->earningsService->earnings($request->user()->id, $months);

        return $this->successResponse(new VolunteerEarningsResource($data));
    }

    public function performance(Request $request): JsonResponse
    {
        $data = $this->performanceService->performance($request->user()->id);

        return $this->successResponse(new VolunteerPerformanceResource($data));
    }

    public function reviews(VolunteerAnalyticsReviewsRequest $request): JsonResponse
    {
        $volunteerId = $request->user()->id;
        $validated = $request->validated();

        $summary = $this->reviewsService->summary($volunteerId);

        $perPage = (int) ($validated['per_page'] ?? 10);
        $ratingFilter = isset($validated['rating']) ? (int) $validated['rating'] : null;

        $paginated = $this->reviewsService->paginated($volunteerId, $perPage, $ratingFilter);

        return $this->successResponse([
            'summary' => $summary,
            'data' => VolunteerReviewItemResource::collection(collect($paginated->items()))->resolve(),
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ]);
    }
}
