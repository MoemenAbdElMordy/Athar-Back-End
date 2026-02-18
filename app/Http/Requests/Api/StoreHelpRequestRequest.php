<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreHelpRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'urgency_level' => ['required', 'string', 'max:10'],
            'assistance_type' => ['required', 'string', 'max:100'],
            'details' => ['nullable', 'string'],

            'from_name' => ['required', 'string', 'max:255'],
            'from_lat' => ['required', 'numeric', 'between:-90,90'],
            'from_lng' => ['required', 'numeric', 'between:-180,180'],

            'to_name' => ['nullable', 'string', 'max:255'],
            'to_lat' => ['nullable', 'numeric', 'between:-90,90'],
            'to_lng' => ['nullable', 'numeric', 'between:-180,180'],

            'name' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'location_text' => ['nullable', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'message' => ['nullable', 'string'],
        ];
    }
}
