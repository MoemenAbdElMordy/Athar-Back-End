<?php

namespace App\Http\Requests\Api;

class StorePlaceReportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $categoryId = $this->input('category_id', $this->input('categoryId'));

        if (is_array($categoryId)) {
            $categoryId = null;
        } elseif (is_string($categoryId)) {
            $normalized = strtolower(trim($categoryId));

            if (in_array($normalized, ['', '0', 'null', 'undefined', 'nan'], true)) {
                $categoryId = null;
            }
        }

        if (!is_null($categoryId) && (!is_numeric($categoryId) || (int) $categoryId <= 0)) {
            $categoryId = null;
        }

        if ($this->has('category_id') || $this->has('categoryId')) {
            $this->merge(['category_id' => $categoryId]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:255'],
            'government_id' => ['required', 'integer', 'exists:governments,id'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],

            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string'],

            'wide_entrance' => ['sometimes', 'boolean'],
            'wheelchair_accessible' => ['sometimes', 'boolean'],
            'elevator_available' => ['sometimes', 'boolean'],
            'ramp_available' => ['sometimes', 'boolean'],
            'parking' => ['sometimes', 'boolean'],
            'accessible_toilet' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
