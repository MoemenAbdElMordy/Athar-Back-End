<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', Rule::in(['general', 'technical', 'account', 'safety'])],
            'priority' => ['nullable', 'string', Rule::in(['low', 'medium', 'high'])],
        ];
    }
}
