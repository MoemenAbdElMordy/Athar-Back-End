<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'payment_id' => $this->id,
            'booking_id' => $this->booking_id,
            'user_id' => $this->user_id,
            'payment_method' => $this->payment_method,
            'amount_cents' => $this->amount_cents,
            'amount_egp' => (float) $this->total_amount,
            'currency' => $this->currency,
            'order_reference' => $this->order_reference,
            'paymob_order_id' => $this->paymob_order_id,
            'paymob_transaction_id' => $this->paymob_transaction_id,
            'checkout_url' => $this->iframe_url,
            'wallet_number' => $this->wallet_number,
            'wallet_redirect_url' => $this->wallet_redirect_url,
            'status' => $this->status,
            'success' => $this->success,
            'paid_at' => $this->paid_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
