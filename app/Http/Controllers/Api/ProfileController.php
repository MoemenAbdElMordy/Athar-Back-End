<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Models\Flag;
use App\Models\HelpRequest;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ApiResponse;

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->validated());
        $user->save();

        return $this->successResponse(new UserResource($user));
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->successResponse([
            'ratings_count' => Review::query()->where('user_id', $user->id)->count(),
            'reports_count' => Flag::query()->where('flagger_id', $user->id)->count(),
            'helpful_count' => HelpRequest::query()
                ->where('volunteer_id', $user->id)
                ->where('status', 'completed')
                ->count(),
        ]);
    }
}
