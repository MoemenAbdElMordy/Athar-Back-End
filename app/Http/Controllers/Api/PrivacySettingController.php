<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdatePrivacySettingRequest;
use App\Models\PrivacySetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PrivacySettingController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $settings = PrivacySetting::query()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return $this->successResponse($this->transform($settings));
    }

    public function update(UpdatePrivacySettingRequest $request): JsonResponse
    {
        $settings = PrivacySetting::query()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $settings->fill($request->validated());
        $settings->save();

        return $this->successResponse($this->transform($settings));
    }

    private function transform(PrivacySetting $settings): array
    {
        return [
            'location_sharing' => (bool) $settings->location_sharing,
            'profile_visibility' => (bool) $settings->profile_visibility,
            'show_ratings' => (bool) $settings->show_ratings,
            'activity_status' => (bool) $settings->activity_status,
            'two_factor_auth' => (bool) $settings->two_factor_auth,
        ];
    }
}
