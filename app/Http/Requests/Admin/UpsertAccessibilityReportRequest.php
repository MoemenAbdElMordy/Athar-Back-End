<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpsertAccessibilityReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'verified' => ['sometimes', 'boolean'],
            'wide_entrance' => ['sometimes', 'boolean'],
            'wheelchair_accessible' => ['sometimes', 'boolean'],
            'elevator_available' => ['sometimes', 'boolean'],
            'ramp_available' => ['sometimes', 'boolean'],
            'parking' => ['sometimes', 'boolean'],
        ];
    }
}
