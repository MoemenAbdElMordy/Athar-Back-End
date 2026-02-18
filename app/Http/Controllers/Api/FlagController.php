<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreFlagRequest;
use App\Models\Companion;
use App\Models\Flag;
use App\Models\Location;
use App\Models\Review;
use Illuminate\Http\JsonResponse;

class FlagController extends Controller
{
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
            return response()->json(['message' => 'Not Found.'], 404);
        }

        $flag = Flag::create([
            'flagger_id' => $user->id,
            'flaggable_type' => $flaggableClass,
            'flaggable_id' => $flaggable->id,
            'reason' => $data['reason'],
            'details' => $data['details'] ?? null,
            'status' => 'open',
        ]);

        return response()->json($flag, 201);
    }
}
