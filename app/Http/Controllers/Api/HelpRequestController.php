<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreHelpRequestRequest;
use App\Models\HelpRequest;
use Illuminate\Http\JsonResponse;

class HelpRequestController extends Controller
{
    public function store(StoreHelpRequestRequest $request): JsonResponse
    {
        $user = $request->user();

        $helpRequest = HelpRequest::create([
            'requester_id' => $user->id,
            'user_id' => $user->id,
            ...$request->validated(),
            'status' => 'pending',
        ]);

        return response()->json($helpRequest, 201);
    }
}
