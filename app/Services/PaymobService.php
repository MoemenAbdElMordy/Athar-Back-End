<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymobService
{
    protected string $baseUrl;
    protected string $apiKey;
    protected int $cardIntegrationId;
    protected int $walletIntegrationId;
    protected string $iframeId;
    protected string $hmacSecret;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('paymob.base_url'), '/');
        $this->apiKey = (string) config('paymob.api_key');
        $this->cardIntegrationId = (int) config('paymob.card_integration_id');
        $this->walletIntegrationId = (int) config('paymob.wallet_integration_id');
        $this->iframeId = (string) config('paymob.iframe_id');
        $this->hmacSecret = (string) config('paymob.hmac_secret');
    }

    // ─── 1. Authenticate ─────────────────────────────────────

    /**
     * Obtain an auth token from Paymob.
     *
     * @throws \RuntimeException
     */
    public function authenticate(): string
    {
        $response = Http::post("{$this->baseUrl}/auth/tokens", [
            'api_key' => $this->apiKey,
        ]);

        if ($response->failed()) {
            Log::error('Paymob authentication failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to authenticate with Paymob.');
        }

        $token = $response->json('token');

        if (! $token) {
            Log::error('Paymob auth response missing token', ['body' => $response->json()]);
            throw new \RuntimeException('Paymob auth token not found in response.');
        }

        return $token;
    }

    // ─── 2. Create Order ──────────────────────────────────────

    /**
     * Register an order with Paymob.
     *
     * @param  string  $authToken
     * @param  int     $amountCents
     * @param  string  $currency
     * @param  string  $merchantOrderId  Unique reference for this order
     * @return string  Paymob order ID
     *
     * @throws \RuntimeException
     */
    public function createOrder(string $authToken, int $amountCents, string $currency, string $merchantOrderId): string
    {
        $response = Http::post("{$this->baseUrl}/ecommerce/orders", [
            'auth_token' => $authToken,
            'delivery_needed' => false,
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'merchant_order_id' => $merchantOrderId,
            'items' => [],
        ]);

        if ($response->failed()) {
            Log::error('Paymob order creation failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to create Paymob order.');
        }

        $orderId = $response->json('id');

        if (! $orderId) {
            Log::error('Paymob order response missing id', ['body' => $response->json()]);
            throw new \RuntimeException('Paymob order ID not found in response.');
        }

        return (string) $orderId;
    }

    // ─── 3. Generate Payment Key ──────────────────────────────

    /**
     * Generate a payment key (token) for a given integration.
     *
     * @param  array{
     *     auth_token: string,
     *     amount_cents: int,
     *     currency: string,
     *     order_id: string,
     *     integration_id: int,
     *     first_name: string,
     *     last_name: string,
     *     email: string,
     *     phone_number: string,
     * } $data
     * @return string  Payment key / token
     *
     * @throws \RuntimeException
     */
    public function generatePaymentKey(array $data): string
    {
        $payload = [
            'auth_token' => $data['auth_token'],
            'amount_cents' => $data['amount_cents'],
            'expiration' => 3600,
            'order_id' => $data['order_id'],
            'billing_data' => [
                'apartment' => 'N/A',
                'email' => $data['email'],
                'floor' => 'N/A',
                'first_name' => $data['first_name'],
                'street' => 'N/A',
                'building' => 'N/A',
                'phone_number' => $data['phone_number'],
                'shipping_method' => 'N/A',
                'postal_code' => 'N/A',
                'city' => 'N/A',
                'country' => 'EG',
                'last_name' => $data['last_name'],
                'state' => 'N/A',
            ],
            'currency' => $data['currency'],
            'integration_id' => $data['integration_id'],
        ];

        $response = Http::post("{$this->baseUrl}/acceptance/payment_keys", $payload);

        if ($response->failed()) {
            Log::error('Paymob payment key generation failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to generate Paymob payment key.');
        }

        $token = $response->json('token');

        if (! $token) {
            Log::error('Paymob payment key response missing token', ['body' => $response->json()]);
            throw new \RuntimeException('Paymob payment key not found in response.');
        }

        return $token;
    }

    // ─── 4. Card Checkout URL ─────────────────────────────────

    /**
     * Full card checkout flow: auth → order → payment key → iframe URL.
     *
     * @param  Payment $payment  The local payment record (status=pending)
     * @param  array   $billing  Billing data (first_name, last_name, email, phone_number)
     * @return array{payment_key: string, checkout_url: string, paymob_order_id: string}
     */
    public function generateCardCheckoutUrl(Payment $payment, array $billing): array
    {
        $authToken = $this->authenticate();
        $orderRef = $payment->order_reference;

        $paymobOrderId = $this->createOrder(
            $authToken,
            $payment->amount_cents,
            $payment->currency,
            $orderRef,
        );

        $paymentKey = $this->generatePaymentKey([
            'auth_token' => $authToken,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'order_id' => $paymobOrderId,
            'integration_id' => $this->cardIntegrationId,
            'first_name' => $billing['first_name'],
            'last_name' => $billing['last_name'],
            'email' => $billing['email'],
            'phone_number' => $billing['phone_number'],
        ]);

        $checkoutUrl = "{$this->baseUrl}/acceptance/iframes/{$this->iframeId}?payment_token={$paymentKey}";

        // Persist Paymob references
        $payment->update([
            'paymob_order_id' => $paymobOrderId,
            'paymob_payment_key' => $paymentKey,
            'iframe_url' => $checkoutUrl,
            'status' => 'initiated',
        ]);

        return [
            'payment_key' => $paymentKey,
            'checkout_url' => $checkoutUrl,
            'paymob_order_id' => $paymobOrderId,
        ];
    }

    // ─── 5. Wallet Payment ────────────────────────────────────

    /**
     * Full wallet flow: auth → order → payment key → wallet pay request.
     *
     * @param  Payment $payment  The local payment record (status=pending)
     * @param  array   $billing  Billing data (first_name, last_name, email, phone_number)
     * @param  string  $walletNumber  The customer's mobile wallet number
     * @return array{paymob_order_id: string, redirect_url: string|null, raw: array}
     */
    public function initiateWalletPayment(Payment $payment, array $billing, string $walletNumber): array
    {
        $authToken = $this->authenticate();
        $orderRef = $payment->order_reference;

        $paymobOrderId = $this->createOrder(
            $authToken,
            $payment->amount_cents,
            $payment->currency,
            $orderRef,
        );

        $paymentKey = $this->generatePaymentKey([
            'auth_token' => $authToken,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'order_id' => $paymobOrderId,
            'integration_id' => $this->walletIntegrationId,
            'first_name' => $billing['first_name'],
            'last_name' => $billing['last_name'],
            'email' => $billing['email'],
            'phone_number' => $billing['phone_number'],
        ]);

        // Initiate wallet payment via Paymob
        $walletResponse = Http::post("{$this->baseUrl}/acceptance/payments/pay", [
            'source' => [
                'identifier' => $walletNumber,
                'subtype' => 'WALLET',
            ],
            'payment_token' => $paymentKey,
        ]);

        if ($walletResponse->failed()) {
            Log::error('Paymob wallet initiation failed', [
                'status' => $walletResponse->status(),
                'body' => $walletResponse->json(),
            ]);
            throw new \RuntimeException('Failed to initiate Paymob wallet payment.');
        }

        $walletData = $walletResponse->json();
        $redirectUrl = $walletData['redirect_url'] ?? $walletData['iframe_redirection_url'] ?? null;

        // Persist Paymob references
        $payment->update([
            'paymob_order_id' => $paymobOrderId,
            'paymob_payment_key' => $paymentKey,
            'wallet_number' => $walletNumber,
            'wallet_redirect_url' => $redirectUrl,
            'raw_response_json' => $walletData,
            'status' => 'initiated',
        ]);

        return [
            'paymob_order_id' => $paymobOrderId,
            'redirect_url' => $redirectUrl,
            'raw' => $walletData,
        ];
    }

    // ─── 6. Verify Callback HMAC ──────────────────────────────

    /**
     * Verify the HMAC signature of a Paymob transaction callback.
     *
     * Paymob concatenates specific fields in a defined order, then HMACs
     * with the merchant's HMAC secret (SHA-512).
     *
     * @param  array  $payload  The callback payload (obj key)
     * @param  string $receivedHmac  The HMAC value sent by Paymob
     * @return bool
     */
    public function verifyCallback(array $payload, string $receivedHmac): bool
    {
        if ($this->hmacSecret === '') {
            Log::warning('Paymob HMAC secret is not configured; skipping verification.');
            return true;
        }

        // Paymob specifies these fields in this exact alphabetical order
        $fields = [
            'amount_cents',
            'created_at',
            'currency',
            'error_occured',
            'has_parent_transaction',
            'id',
            'integration_id',
            'is_3d_secure',
            'is_auth',
            'is_capture',
            'is_refunded',
            'is_standalone_payment',
            'is_voided',
            'order.id',
            'owner',
            'pending',
            'source_data.pan',
            'source_data.sub_type',
            'source_data.type',
            'success',
        ];

        $concatenated = '';
        foreach ($fields as $field) {
            $value = data_get($payload, $field, '');
            // Booleans must be lowercased
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $concatenated .= (string) $value;
        }

        $computed = hash_hmac('sha512', $concatenated, $this->hmacSecret);

        return hash_equals($computed, $receivedHmac);
    }

    // ─── 7. Handle Transaction Callback ───────────────────────

    /**
     * Process a Paymob transaction callback and update local payment.
     *
     * @param  array $payload  Full callback payload
     * @return Payment|null  The updated payment, or null if not found
     */
    public function handleTransactionCallback(array $payload): ?Payment
    {
        $obj = $payload['obj'] ?? $payload;

        $paymobOrderId = (string) data_get($obj, 'order.id', data_get($obj, 'order_id'));
        $transactionId = (string) data_get($obj, 'id', '');
        $isSuccess = (bool) data_get($obj, 'success', false);
        $merchantOrderId = (string) data_get($obj, 'order.merchant_order_id',
                            data_get($obj, 'merchant_order_id', ''));

        // Try to find payment by paymob_order_id first, then by order_reference
        $payment = Payment::query()
            ->where('paymob_order_id', $paymobOrderId)
            ->first();

        if (! $payment && $merchantOrderId !== '') {
            $payment = Payment::query()
                ->where('order_reference', $merchantOrderId)
                ->first();
        }

        if (! $payment) {
            Log::warning('Paymob callback: no matching payment found', [
                'paymob_order_id' => $paymobOrderId,
                'merchant_order_id' => $merchantOrderId,
                'transaction_id' => $transactionId,
            ]);
            return null;
        }

        if ($isSuccess) {
            $payment->markAsPaid($transactionId, $payload);

            // Auto-confirm linked help request when payment succeeds
            if ($payment->help_request_id) {
                $helpRequest = $payment->helpRequest;
                if ($helpRequest && $helpRequest->status === 'pending_payment') {
                    $helpRequest->update(['status' => 'active']);
                    Log::info('Help request auto-confirmed after payment', [
                        'help_request_id' => $helpRequest->id,
                        'payment_id' => $payment->id,
                    ]);
                }
            }
        } else {
            $payment->markAsFailed($transactionId, $payload);
        }

        Log::info('Paymob callback processed', [
            'payment_id' => $payment->id,
            'transaction_id' => $transactionId,
            'success' => $isSuccess,
        ]);

        return $payment;
    }

    /**
     * Pull latest transaction status from Paymob and update local payment row.
     *
     * @throws \RuntimeException
     */
    public function refreshPaymentStatus(Payment $payment): Payment
    {
        if (! $payment->paymob_order_id) {
            throw new \RuntimeException('Payment is missing paymob_order_id.');
        }

        $authToken = $this->authenticate();

        $response = Http::withToken($authToken)->get("{$this->baseUrl}/acceptance/transactions", [
            'order_id' => $payment->paymob_order_id,
        ]);

        if ($response->failed()) {
            Log::error('Paymob transaction refresh failed', [
                'status' => $response->status(),
                'payment_id' => $payment->id,
                'paymob_order_id' => $payment->paymob_order_id,
                'body' => $response->json(),
            ]);
            throw new \RuntimeException('Failed to refresh payment status from Paymob.');
        }

        $payload = $response->json();
        $transactions = data_get($payload, 'results', []);

        if (! is_array($transactions) || $transactions === []) {
            Log::warning('Paymob transaction refresh returned no transactions', [
                'payment_id' => $payment->id,
                'paymob_order_id' => $payment->paymob_order_id,
                'body' => $payload,
            ]);
            return $payment->fresh();
        }

        $latest = collect($transactions)->sortByDesc('created_at')->first();
        $isSuccess = (bool) data_get($latest, 'success', false);
        $transactionId = (string) data_get($latest, 'id', '');

        if ($isSuccess) {
            $payment->markAsPaid($transactionId, [
                'source' => 'status_refresh',
                'paymob' => $latest,
            ]);
        } else {
            $payment->markAsFailed($transactionId, [
                'source' => 'status_refresh',
                'paymob' => $latest,
            ]);
        }

        return $payment->fresh();
    }

    // ─── Helpers ──────────────────────────────────────────────

    /**
     * Generate a unique order reference.
     */
    public static function generateOrderReference(): string
    {
        return 'ATHAR-' . strtoupper(Str::random(8)) . '-' . time();
    }
}
