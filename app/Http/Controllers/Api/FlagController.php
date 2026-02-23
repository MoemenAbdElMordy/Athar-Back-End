<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreLocationFlagRequest;
use App\Http\Resources\FlagResource;
use App\Http\Requests\Api\StoreFlagRequest;
use App\Models\Companion;
use App\Models\Flag;
use App\Models\Location;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FlagController extends Controller
{
    use ApiResponse;

    public function store(StoreFlagRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $typeMap = [
            'location' => Location::class,
            'review' => Review::class,
            'companion' => Companion::class,
        ];

        $flaggableClass = $typeMap[$data['flaggable_type']];
        $flaggable = $flaggableClass::find($data['flaggable_id']);

        if (!$flaggable) {
            return $this->errorResponse('Not Found.', [], 404);
        }

        $flag = Flag::create([
            'flagger_id' => $user->id,
            'flaggable_type' => $flaggableClass,
            'flaggable_id' => $flaggable->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'pending',
        ]);

        return $this->successResponse(new FlagResource($flag), null, 201);
    }

    public function storeForLocation(StoreLocationFlagRequest $request, int $id): JsonResponse
    {
        $location = Location::query()->find($id);

        if (!$location) {
            return $this->errorResponse('Location not found.', [], 404);
        }

        $data = $request->validated();

        $flag = Flag::query()->create([
            'flagger_id' => $request->user()->id,
            'flaggable_type' => Location::class,
            'flaggable_id' => $location->id,
            'reason' => $data['type'],
            'details' => $data['details'] ?? null,
            'status' => 'pending',
        ]);

        return $this->successResponse(new FlagResource($flag), null, 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,resolved,dismissed,need_info'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Flag::query()
            ->where('flagger_id', $request->user()->id)
            ->where('flaggable_type', Location::class)
            ->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $flags = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();

        return $this->successResponse($this->paginatedData($flags, FlagResource::collection($flags->getCollection())));
    }
}
