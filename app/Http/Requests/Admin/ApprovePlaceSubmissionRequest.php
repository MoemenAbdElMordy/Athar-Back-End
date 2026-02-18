<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ApprovePlaceSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'create_location' => ['sometimes', 'boolean'],
            'government_id' => ['required_if:create_location,true', 'integer', 'exists:governments,id'],
        ];
    }
}
