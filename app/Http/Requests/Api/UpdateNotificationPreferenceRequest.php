<?php

namespace App\Http\Requests\Api;

class UpdateNotificationPreferenceRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'push_enabled' => ['sometimes', 'boolean'],
            'email_enabled' => ['sometimes', 'boolean'],
            'sms_enabled' => ['sometimes', 'boolean'],
            'volunteer_requests' => ['sometimes', 'boolean'],
            'volunteer_accepted' => ['sometimes', 'boolean'],
            'location_updates' => ['sometimes', 'boolean'],
            'new_ratings' => ['sometimes', 'boolean'],
            'community_updates' => ['sometimes', 'boolean'],
            'marketing_emails' => ['sometimes', 'boolean'],
            'sound_enabled' => ['sometimes', 'boolean'],
            'vibration_enabled' => ['sometimes', 'boolean'],
            'quiet_hours_start' => ['sometimes', 'nullable', 'date_format:H:i'],
            'quiet_hours_end' => ['sometimes', 'nullable', 'date_format:H:i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'push_enabled' => $this->input('push_enabled', $this->input('pushEnabled')),
            'email_enabled' => $this->input('email_enabled', $this->input('emailEnabled')),
            'sms_enabled' => $this->input('sms_enabled', $this->input('smsEnabled')),
            'volunteer_requests' => $this->input('volunteer_requests', $this->input('volunteerRequests')),
            'volunteer_accepted' => $this->input('volunteer_accepted', $this->input('volunteerAccepted')),
            'location_updates' => $this->input('location_updates', $this->input('locationUpdates')),
            'new_ratings' => $this->input('new_ratings', $this->input('newRatings')),
            'community_updates' => $this->input('community_updates', $this->input('communityUpdates')),
            'marketing_emails' => $this->input('marketing_emails', $this->input('marketingEmails')),
            'sound_enabled' => $this->input('sound_enabled', $this->input('soundEnabled')),
            'vibration_enabled' => $this->input('vibration_enabled', $this->input('vibrationEnabled')),
            'quiet_hours_start' => $this->input('quiet_hours_start', $this->input('quietHoursStart')),
            'quiet_hours_end' => $this->input('quiet_hours_end', $this->input('quietHoursEnd')),
        ]);
    }
}
