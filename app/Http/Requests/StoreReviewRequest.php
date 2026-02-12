<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'companion_id' => ['nullable', 'integer', 'exists:companions,id'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $companionId = $this->input('companion_id');
            $locationId = $this->input('location_id');

            $hasCompanion = !is_null($companionId);
            $hasLocation = !is_null($locationId);

            if ($hasCompanion === $hasLocation) {
                $validator->errors()->add('target', 'Exactly one of companion_id or location_id must be provided.');
            }
        });
    }
}
