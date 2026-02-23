<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePlaceSubmissionRequest;
use App\Http\Resources\PlaceSubmissionResource;
use App\Models\PlaceSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PlaceSubmissionController extends Controller
{
    use ApiResponse;

    public function store(StorePlaceSubmissionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $submission = PlaceSubmission::create([
            'submitted_by' => $user->id,
            'name' => $data['name'],
            'address' => $data['address'],
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'category_id' => $data['category_id'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'pending',
        ]);

        return $this->successResponse(new PlaceSubmissionResource($submission), null, 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:pending,approved,rejected'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = PlaceSubmission::query()->where('submitted_by', $request->user()->id)->latest();

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $submissions = $query->paginate((int) ($validated['per_page'] ?? 15))->withQueryString();

        return $this->successResponse(
            $this->paginatedData($submissions, PlaceSubmissionResource::collection($submissions->getCollection()))
        );
    }
}
