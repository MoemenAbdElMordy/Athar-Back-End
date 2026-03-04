<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class UpdateProfileRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'full_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->user()?->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'disability_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mobility_aids' => ['sometimes', 'nullable', 'string', 'max:255'],
            'assistance_needs' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->input('full_name', $this->input('fullName')),
            'city' => $this->input('city', $this->input('address')),
            'disability_type' => $this->input('disability_type', $this->input('disabilityType')),
            'assistance_needs' => $this->input('assistance_needs', $this->input('assistanceNeeds')),
            'emergency_contact_name' => $this->input('emergency_contact_name', $this->input('emergencyContact')),
            'emergency_contact_phone' => $this->input('emergency_contact_phone', $this->input('emergencyPhone')),
        ]);
    }
}
