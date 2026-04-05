<?php

namespace App\Http\Requests\Api;

class CardCheckoutRequest extends ApiFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'booking_id' => ['nullable', 'integer', 'exists:bookings,id'],
            'help_request_id' => ['nullable', 'integer', 'exists:help_requests,id'],
            'request_id' => ['nullable', 'integer', 'exists:help_requests,id'],
            'user_id' => ['prohibited'],
            'amount_egp' => ['required', 'numeric', 'min:1'],
            'currency' => ['required', 'string', 'in:EGP'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
        ];
    }

    /**
     * Computed amount in cents (piasters).
     */
    public function amountCents(): int
    {
        return (int) round($this->validated('amount_egp') * 100);
    }
}
