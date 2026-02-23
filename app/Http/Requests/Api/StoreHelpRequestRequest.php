<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreHelpRequestRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'urgency' => ['required', 'string', Rule::in(['low', 'medium', 'high'])],
            'assistance_type' => ['required', 'string', Rule::in(['navigation', 'finding_location', 'check_in', 'other'])],
            'details' => ['nullable', 'string'],

            'from_label' => ['required', 'string', 'max:255'],
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],

            'to_label' => ['required', 'string', 'max:255'],
            'to_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'to_lng' => ['nullable', 'numeric', 'between:-180,180'],
        ];
    }
}
