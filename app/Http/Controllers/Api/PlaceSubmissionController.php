<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePlaceSubmissionRequest;
use App\Models\PlaceSubmission;
use Illuminate\Http\JsonResponse;

class PlaceSubmissionController extends Controller
{
    public function store(StorePlaceSubmissionRequest $request): JsonResponse
    {
        $user = $request->user();

        $submission = PlaceSubmission::create([
            'submitted_by' => $user->id,
            ...$request->validated(),
            'status' => 'pending',
        ]);

        return response()->json($submission, 201);
    }
}
