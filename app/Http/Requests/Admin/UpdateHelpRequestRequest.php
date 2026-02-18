<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateHelpRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', Rule::in(['pending', 'in_progress', 'resolved'])],
            'assigned_admin_id' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
        ];
    }
}
