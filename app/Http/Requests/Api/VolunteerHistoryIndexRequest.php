<?php

namespace App\Http\Requests\Api;

class VolunteerHistoryIndexRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'in:completed,cancelled,active,all'],
            'from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:from'],
            'assistance_type' => ['nullable', 'string', 'max:100'],
            'sort_by' => ['nullable', 'string', 'in:completed_at,created_at,service_fee,net_amount_cents'],
            'sort_direction' => ['nullable', 'string', 'in:asc,desc'],
        ];
    }
}
