<?php

namespace App\Http\Requests\Api;

class VolunteerAnalyticsReviewsRequest extends ApiFormRequest
{
    public function rules(): array
    {
        return [
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
        ];
    }
}
