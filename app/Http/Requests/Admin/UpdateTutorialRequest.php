<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTutorialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'video_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'thumbnail_url' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'is_published' => ['sometimes', 'boolean'],
        ];
    }
}
