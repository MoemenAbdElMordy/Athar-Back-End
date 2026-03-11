<?php

namespace Tests\Feature;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('paymob.base_url', 'https://accept.paymob.com/api');
        config()->set('paymob.api_key', 'test_key');
        config()->set('paymob.card_integration_id', 1111);
        config()->set('paymob.wallet_integration_id', 2222);
        config()->set('paymob.iframe_id', '98765');
        config()->set('paymob.hmac_secret', '');
    }

    public function test_card_checkout_creates_payment_and_returns_checkout_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'auth_token_test',
            ], 200),
            'https://accept.paymob.com/api/ecommerce/orders' => Http::response([
                'id' => 123456789,
            ], 200),
            'https://accept.paymob.com/api/acceptance/payment_keys' => Http::response([
                'token' => 'payment_token_test',
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/card/checkout', [
            'booking_id' => null,
            'user_id' => $user->id,
            'amount_egp' => 100,
            'currency' => 'EGP',
            'first_name' => 'Sara',
            'last_name' => 'Mohammed',
            'email' => 'sara@example.com',
            'phone_number' => '01012345678',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'card')
            ->assertJsonPath('data.amount_cents', 10000)
            ->assertJsonPath('data.paymob_order_id', '123456789');

        $paymentId = $response->json('data.payment_id');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'payment_method' => 'card',
            'amount_cents' => 10000,
            'status' => 'initiated',
            'paymob_order_id' => '123456789',
        ]);

        $payment = Payment::findOrFail($paymentId);
        $this->assertStringContainsString('/acceptance/iframes/98765', (string) $payment->iframe_url);
    }

    public function test_wallet_checkout_creates_payment_and_returns_redirect_url(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        Http::fake([
            'https://accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'auth_token_test',
            ], 200),
            'https://accept.paymob.com/api/ecommerce/orders' => Http::response([
                'id' => 123456790,
            ], 200),
            'https://accept.paymob.com/api/acceptance/payment_keys' => Http::response([
                'token' => 'wallet_payment_token_test',
            ], 200),
            'https://accept.paymob.com/api/acceptance/payments/pay' => Http::response([
                'redirect_url' => 'https://wallet.paymob.com/redirect/abc123',
                'pending' => true,
            ], 200),
        ]);

        $response = $this->postJson('/api/payments/wallet/checkout', [
            'booking_id' => null,
            'user_id' => $user->id,
            'amount_egp' => 100,
            'currency' => 'EGP',
            'first_name' => 'Sara',
            'last_name' => 'Mohammed',
            'email' => 'sara@example.com',
            'phone_number' => '01012345678',
            'wallet_number' => '01012345678',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_method', 'wallet')
            ->assertJsonPath('data.amount_cents', 10000)
            ->assertJsonPath('data.paymob_order_id', '123456790')
            ->assertJsonPath('data.redirect_url', 'https://wallet.paymob.com/redirect/abc123');

        $paymentId = $response->json('data.payment_id');

        $this->assertDatabaseHas('payments', [
            'id' => $paymentId,
            'payment_method' => 'wallet',
            'wallet_number' => '01012345678',
            'status' => 'initiated',
            'paymob_order_id' => '123456790',
        ]);
    }

    public function test_callback_marks_payment_paid(): void
    {
        $payment = Payment::create([
            'booking_id' => null,
            'user_id' => null,
            'payment_method' => 'card',
            'amount_cents' => 10000,
            'total_amount' => 100,
            'currency' => 'EGP',
            'order_reference' => 'ATHAR-TEST-ORDER',
            'paymob_order_id' => '555777',
            'status' => 'initiated',
            'success' => false,
        ]);

        $payload = [
            'obj' => [
                'id' => 999111,
                'success' => true,
                'order' => [
                    'id' => 555777,
                    'merchant_order_id' => 'ATHAR-TEST-ORDER',
                ],
            ],
        ];

        $this->postJson('/api/payments/paymob/callback', $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.status', 'paid');

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'success' => true,
            'paymob_transaction_id' => '999111',
        ]);
    }

    public function test_refresh_endpoint_updates_status_from_paymob_transactions_api(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $payment = Payment::create([
            'booking_id' => null,
            'user_id' => null,
            'payment_method' => 'card',
            'amount_cents' => 10000,
            'total_amount' => 100,
            'currency' => 'EGP',
            'order_reference' => 'ATHAR-REFRESH-ORDER',
            'paymob_order_id' => '888999',
            'status' => 'initiated',
            'success' => false,
        ]);

        Http::fake([
            'https://accept.paymob.com/api/auth/tokens' => Http::response([
                'token' => 'auth_token_refresh',
            ], 200),
            'https://accept.paymob.com/api/acceptance/transactions*' => Http::response([
                'results' => [
                    [
                        'id' => 321654,
                        'success' => true,
                        'created_at' => now()->toIso8601String(),
                    ],
                ],
            ], 200),
        ]);

        $this->postJson("/api/payments/{$payment->id}/refresh")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.payment_id', $payment->id)
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.success', true);

        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'success' => true,
            'paymob_transaction_id' => '321654',
        ]);
    }
}
