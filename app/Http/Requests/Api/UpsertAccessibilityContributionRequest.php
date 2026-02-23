<?php

namespace App\Http\Requests\Api;

class UpsertAccessibilityContributionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wide_entrance' => ['sometimes', 'boolean'],
            'wheelchair_accessible' => ['sometimes', 'boolean'],
            'elevator_available' => ['sometimes', 'boolean'],
            'ramp_available' => ['sometimes', 'boolean'],
            'parking' => ['sometimes', 'boolean'],
            'accessible_toilet' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
