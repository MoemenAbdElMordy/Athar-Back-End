<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateNotificationPreferenceRequest;
use App\Models\NotificationPreference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    use ApiResponse;

    public function show(Request $request): JsonResponse
    {
        $preferences = NotificationPreference::query()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return $this->successResponse($this->transform($preferences));
    }

    public function update(UpdateNotificationPreferenceRequest $request): JsonResponse
    {
        $preferences = NotificationPreference::query()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        $preferences->fill($request->validated());
        $preferences->save();

        return $this->successResponse($this->transform($preferences));
    }

    private function transform(NotificationPreference $preferences): array
    {
        return [
            'push_enabled' => (bool) $preferences->push_enabled,
            'email_enabled' => (bool) $preferences->email_enabled,
            'sms_enabled' => (bool) $preferences->sms_enabled,
            'volunteer_requests' => (bool) $preferences->volunteer_requests,
            'volunteer_accepted' => (bool) $preferences->volunteer_accepted,
            'location_updates' => (bool) $preferences->location_updates,
            'new_ratings' => (bool) $preferences->new_ratings,
            'community_updates' => (bool) $preferences->community_updates,
            'marketing_emails' => (bool) $preferences->marketing_emails,
            'sound_enabled' => (bool) $preferences->sound_enabled,
            'vibration_enabled' => (bool) $preferences->vibration_enabled,
            'quiet_hours_start' => $preferences->quiet_hours_start,
            'quiet_hours_end' => $preferences->quiet_hours_end,
        ];
    }
}
