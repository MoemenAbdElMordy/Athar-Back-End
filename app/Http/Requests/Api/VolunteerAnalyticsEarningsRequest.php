<?php

namespace App\Http\Requests\Api;

class VolunteerAnalyticsEarningsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'months' => ['nullable', 'integer', 'min:1', 'max:24'],
        ];
    }
}
