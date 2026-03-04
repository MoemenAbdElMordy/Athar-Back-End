<?php

namespace App\Http\Requests\Api;

class UpdatePrivacySettingRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'location_sharing' => ['sometimes', 'boolean'],
            'profile_visibility' => ['sometimes', 'boolean'],
            'show_ratings' => ['sometimes', 'boolean'],
            'activity_status' => ['sometimes', 'boolean'],
            'two_factor_auth' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'location_sharing' => $this->input('location_sharing', $this->input('locationSharing')),
            'profile_visibility' => $this->input('profile_visibility', $this->input('profileVisibility')),
            'show_ratings' => $this->input('show_ratings', $this->input('showRatings')),
            'activity_status' => $this->input('activity_status', $this->input('activityStatus')),
            'two_factor_auth' => $this->input('two_factor_auth', $this->input('twoFactorAuth')),
        ]);
    }
}
