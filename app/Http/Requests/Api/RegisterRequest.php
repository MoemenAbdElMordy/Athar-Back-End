<?php

namespace App\Http\Requests\Api;

class RegisterRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:255'],
            'full_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'in:user,volunteer'],
            'city' => ['nullable', 'string', 'max:255', 'required_if:role,volunteer'],
            'national_id' => ['nullable', 'string', 'max:255', 'required_if:role,volunteer'],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'required_if:role,volunteer'],
            'id_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120', 'required_if:role,volunteer'],
            'certification_document' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'certification' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'volunteer_languages' => ['nullable', 'array', 'required_if:role,volunteer', 'min:1'],
            'volunteer_languages.*' => ['string', 'max:100'],
            'volunteer_availability' => ['nullable', 'array', 'required_if:role,volunteer', 'min:1'],
            'volunteer_availability.*' => ['string', 'max:100'],
            'volunteer_motivation' => ['nullable', 'string', 'max:2000', 'required_if:role,volunteer'],
            'disability_type' => ['nullable', 'string', 'max:255'],
            'mobility_aids' => ['nullable', 'string', 'max:255'],
            'assistance_needs' => ['nullable', 'string', 'max:2000'],
            'emergency_contact_name' => ['nullable', 'string', 'max:255'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'full_name' => $this->input('full_name', $this->input('fullName')),
            'city' => $this->input('city', $this->input('location')),
            'national_id' => $this->input('national_id', $this->input('national_id_or_iqama')),
            'date_of_birth' => $this->input('date_of_birth', $this->input('dateOfBirth')),
            'volunteer_languages' => $this->input('volunteer_languages', $this->input('languages')),
            'volunteer_availability' => $this->input('volunteer_availability', $this->input('availability')),
            'volunteer_motivation' => $this->input('volunteer_motivation', $this->input('motivation')),
            'disability_type' => $this->input('disability_type', $this->input('disabilityType')),
            'assistance_needs' => $this->input('assistance_needs', $this->input('assistanceNeeds', $this->input('accessibility_needs'))),
            'emergency_contact_name' => $this->input('emergency_contact_name', $this->input('emergencyContact', $this->input('emergency_contact'))),
            'emergency_contact_phone' => $this->input('emergency_contact_phone', $this->input('emergencyPhone', $this->input('emergency_phone'))),
        ]);
    }
}
