<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreFlagRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'flaggable_type' => ['required', 'string', Rule::in(['location', 'review', 'companion'])],
            'flaggable_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:30'],
            'details' => ['nullable', 'string'],
        ];
    }
}
