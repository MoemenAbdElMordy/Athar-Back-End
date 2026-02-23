<?php

namespace App\Http\Requests\Api;

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
            'phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'disability_type' => ['sometimes', 'nullable', 'string', 'max:255'],
            'mobility_aids' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
