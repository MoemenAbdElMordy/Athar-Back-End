<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreLocationFlagRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(['wrong_info', 'closed', 'not_accessible', 'other'])],
            'details' => ['nullable', 'string'],
        ];
    }
}
