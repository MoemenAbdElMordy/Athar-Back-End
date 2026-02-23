<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Resources\GovernmentResource;
use App\Models\Government;
use Illuminate\Http\JsonResponse;

class GovernmentController extends Controller
{
    use ApiResponse;

    public function index(): JsonResponse
    {
        $governments = Government::query()->orderBy('id')->get();

        return $this->successResponse(GovernmentResource::collection($governments));
    }
}
