<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CardCheckoutRequest;
use App\Http\Requests\Api\WalletCheckoutRequest;
use App\Http\Resources\PaymentResource;
use App\Models\Payment;
use App\Services\PaymobService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    use ApiResponse;

    public function __construct(
        protected PaymobService $paymobService,
    ) {}

    // ─── A) Card Checkout ─────────────────────────────────────

    /**
     * POST /api/payments/card/checkout
     *
     * Create a Paymob card payment session and return the checkout URL.
     */
    public function cardCheckout(CardCheckoutRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $amountCents = $request->amountCents();

            // 1. Create local payment record
            $payment = Payment::create([
                'booking_id' => $data['booking_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'payment_method' => 'card',
                'amount_cents' => $amountCents,
                'total_amount' => $data['amount_egp'],
                'currency' => $data['currency'],
                'order_reference' => PaymobService::generateOrderReference(),
                'status' => 'pending',
                'raw_request_json' => $data,
            ]);

            // 2. Run Paymob card flow
            $result = $this->paymobService->generateCardCheckoutUrl($payment, [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
            ]);

            return $this->successResponse([
                'payment_id' => $payment->id,
                'payment_method' => 'card',
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'paymob_order_id' => $result['paymob_order_id'],
                'payment_token' => $result['payment_key'],
                'checkout_url' => $result['checkout_url'],
                'status' => $payment->fresh()->status,
            ], 'Card checkout session created successfully.');

        } catch (\Throwable $e) {
            Log::error('Card checkout failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Unable to process card payment at the moment.',
                [],
                500,
            );
        }
    }

    // ─── B) Wallet Checkout ───────────────────────────────────

    /**
     * POST /api/payments/wallet/checkout
     *
     * Initiate a mobile wallet payment via Paymob.
     */
    public function walletCheckout(WalletCheckoutRequest $request): JsonResponse
    {
        try {
            $data = $request->validated();
            $amountCents = $request->amountCents();

            // 1. Create local payment record
            $payment = Payment::create([
                'booking_id' => $data['booking_id'] ?? null,
                'user_id' => $data['user_id'] ?? null,
                'payment_method' => 'wallet',
                'amount_cents' => $amountCents,
                'total_amount' => $data['amount_egp'],
                'currency' => $data['currency'],
                'order_reference' => PaymobService::generateOrderReference(),
                'wallet_number' => $data['wallet_number'],
                'status' => 'pending',
                'raw_request_json' => $data,
            ]);

            // 2. Run Paymob wallet flow
            $result = $this->paymobService->initiateWalletPayment($payment, [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'],
                'email' => $data['email'],
                'phone_number' => $data['phone_number'],
            ], $data['wallet_number']);

            return $this->successResponse([
                'payment_id' => $payment->id,
                'payment_method' => 'wallet',
                'amount_cents' => $payment->amount_cents,
                'currency' => $payment->currency,
                'paymob_order_id' => $result['paymob_order_id'],
                'wallet_number' => $data['wallet_number'],
                'redirect_url' => $result['redirect_url'],
                'status' => $payment->fresh()->status,
            ], 'Wallet payment initiated successfully.');

        } catch (\Throwable $e) {
            Log::error('Wallet checkout failed', [
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse(
                'Unable to process wallet payment at the moment.',
                [],
                500,
            );
        }
    }

    // ─── C) Paymob Callback / Webhook ─────────────────────────

    /**
     * POST /api/payments/paymob/callback
     *
     * Receives Paymob transaction processed callback.
     * This endpoint must be publicly accessible (no auth middleware).
     */
    public function callback(Request $request): JsonResponse
    {
        try {
            $payload = $request->all();

            Log::info('Paymob callback received', ['keys' => array_keys($payload)]);

            // HMAC verification (Paymob sends hmac in query string or header)
            $receivedHmac = $request->query('hmac', $request->header('X-Hmac', ''));

            $obj = $payload['obj'] ?? $payload;

            if ($receivedHmac && ! $this->paymobService->verifyCallback($obj, $receivedHmac)) {
                Log::warning('Paymob callback HMAC verification failed');
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid HMAC signature.',
                ], 403);
            }

            // Process the callback
            $payment = $this->paymobService->handleTransactionCallback($payload);

            if (! $payment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment not found for this callback.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Callback processed.',
                'data' => [
                    'payment_id' => $payment->id,
                    'status' => $payment->status,
                    'success' => $payment->success,
                ],
            ]);

        } catch (\Throwable $e) {
            Log::error('Paymob callback processing failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Callback processing error.',
            ], 500);
        }
    }

    // ─── D) Show Payment Status ───────────────────────────────

    /**
     * GET /api/payments/{id}
     *
     * Fetch payment status for polling from the mobile app.
     */
    public function show(int $id): JsonResponse
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return $this->errorResponse('Payment not found.', [], 404);
        }

        return $this->successResponse(new PaymentResource($payment));
    }

    /**
     * POST /api/payments/{id}/refresh
     *
     * Fallback polling refresh that pulls latest status from Paymob.
     */
    public function refresh(int $id): JsonResponse
    {
        $payment = Payment::find($id);

        if (! $payment) {
            return $this->errorResponse('Payment not found.', [], 404);
        }

        try {
            $updatedPayment = $this->paymobService->refreshPaymentStatus($payment);

            return $this->successResponse(
                new PaymentResource($updatedPayment),
                'Payment status refreshed successfully.',
            );
        } catch (\Throwable $e) {
            Log::error('Payment refresh failed', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);

            return $this->errorResponse('Unable to refresh payment status at the moment.', [], 500);
        }
    }
}
