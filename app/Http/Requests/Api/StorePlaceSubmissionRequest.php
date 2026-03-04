<?php

namespace App\Http\Requests\Api;

class StorePlaceSubmissionRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $categoryId = $this->input('category_id', $this->input('categoryId'));

        if ($categoryId === '' || $categoryId === null || $categoryId === 'null') {
            $categoryId = null;
        } elseif (is_numeric($categoryId) && (int) $categoryId <= 0) {
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
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
