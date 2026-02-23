<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpsertAccessibilityContributionRequest;
use App\Http\Resources\AccessibilityContributionResource;
use App\Models\AccessibilityContribution;
use App\Models\Location;
use Illuminate\Http\JsonResponse;

class AccessibilityContributionController extends Controller
{
    use ApiResponse;

    public function upsert(UpsertAccessibilityContributionRequest $request, int $id): JsonResponse
    {
        $location = Location::query()->find($id);

        if (!$location) {
            return $this->errorResponse('Location not found.', [], 404);
        }

        $data = $request->validated();

        unset($data['verified']);

        $contribution = AccessibilityContribution::query()->updateOrCreate(
            [
                'location_id' => $location->id,
                'user_id' => $request->user()->id,
            ],
            [
                ...$data,
                'status' => 'pending',
                'verified_at' => null,
                'verified_by_admin_id' => null,
            ]
        );

        return $this->successResponse(new AccessibilityContributionResource($contribution));
    }
}
