<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'booking_id',
        'user_id',
        'help_request_id',
        'payment_method',
        'amount_cents',
        'total_amount',
        'currency',
        'order_reference',
        'paymob_order_id',
        'paymob_transaction_id',
        'paymob_payment_key',
        'iframe_url',
        'wallet_number',
        'wallet_redirect_url',
        'status',
        'success',
        'raw_request_json',
        'raw_response_json',
        'callback_payload_json',
        'paid_at',
    ];

    protected $attributes = [
        'currency' => 'EGP',
        'status' => 'pending',
        'success' => false,
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'total_amount' => 'decimal:2',
            'success' => 'boolean',
            'raw_request_json' => 'array',
            'raw_response_json' => 'array',
            'callback_payload_json' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    // ── Relationships ────────────────────────────────────────

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function helpRequest(): BelongsTo
    {
        return $this->belongsTo(HelpRequest::class);
    }

    public function bank(): HasOne
    {
        return $this->hasOne(Bank::class);
    }

    // ── Helpers ──────────────────────────────────────────────

    public function isPaid(): bool
    {
        return $this->status === 'paid' && $this->success;
    }

    public function markAsPaid(string $transactionId, ?array $callbackPayload = null): void
    {
        $this->update([
            'status' => 'paid',
            'success' => true,
            'paymob_transaction_id' => $transactionId,
            'callback_payload_json' => $callbackPayload,
            'paid_at' => now(),
        ]);
    }

    public function markAsFailed(string $transactionId, ?array $callbackPayload = null): void
    {
        $this->update([
            'status' => 'failed',
            'success' => false,
            'paymob_transaction_id' => $transactionId,
            'callback_payload_json' => $callbackPayload,
        ]);
    }
}
