<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rule;

class StoreDataExportRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['string', Rule::in(['profile', 'assistance_history', 'location_ratings', 'account_settings'])],
            'format' => ['nullable', 'string', Rule::in(['json', 'csv'])],
        ];
    }

    protected function prepareForValidation(): void
    {
        $categories = $this->input('categories', []);

        if (is_array($categories)) {
            $categories = array_map(function (mixed $category): mixed {
                if (!is_string($category)) {
                    return $category;
                }

                return match ($category) {
                    'assistanceHistory' => 'assistance_history',
                    'locationRatings' => 'location_ratings',
                    'accountSettings' => 'account_settings',
                    default => $category,
                };
            }, $categories);
        }

        $this->merge([
            'categories' => $categories,
        ]);
    }
}
