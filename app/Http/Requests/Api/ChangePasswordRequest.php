<?php

namespace App\Http\Requests\Api;

use Illuminate\Validation\Rules\Password;

class ChangePasswordRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'confirmed', Password::min(8)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'current_password' => $this->input('current_password', $this->input('currentPassword')),
            'new_password' => $this->input('new_password', $this->input('newPassword')),
            'new_password_confirmation' => $this->input('new_password_confirmation', $this->input('confirmPassword')),
        ]);
    }
}
