<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreDataExportRequest;
use App\Models\DataExportRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DataExportRequestController extends Controller
{
    use ApiResponse;

    public function store(StoreDataExportRequest $request): JsonResponse
    {
        $data = $request->validated();

        $exportRequest = DataExportRequest::query()->create([
            'user_id' => $request->user()->id,
            'categories' => $data['categories'],
            'format' => $data['format'] ?? 'json',
            'status' => 'pending',
        ]);

        return $this->successResponse($this->transform($exportRequest), null, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $exportRequest = DataExportRequest::query()
            ->where('user_id', $request->user()->id)
            ->find($id);

        if (!$exportRequest) {
            return $this->errorResponse('Data export request not found.', [], 404);
        }

        return $this->successResponse($this->transform($exportRequest));
    }

    private function transform(DataExportRequest $exportRequest): array
    {
        return [
            'id' => $exportRequest->id,
            'categories' => $exportRequest->categories,
            'format' => $exportRequest->format,
            'status' => $exportRequest->status,
            'download_url' => $exportRequest->download_url,
            'expires_at' => optional($exportRequest->expires_at)->toIso8601String(),
            'processed_at' => optional($exportRequest->processed_at)->toIso8601String(),
            'created_at' => optional($exportRequest->created_at)->toIso8601String(),
        ];
    }
}
