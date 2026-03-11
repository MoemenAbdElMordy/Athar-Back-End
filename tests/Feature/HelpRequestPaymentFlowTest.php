<?php

namespace Tests\Feature;

use App\Models\HelpRequest;
use App\Models\Notification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HelpRequestPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected User $requester;
    protected User $volunteer;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('paymob.base_url', 'https://accept.paymob.com/api');
        config()->set('paymob.api_key', 'test_key');
        config()->set('paymob.card_integration_id', 1111);
        config()->set('paymob.wallet_integration_id', 2222);
        config()->set('paymob.iframe_id', '98765');
        config()->set('paymob.hmac_secret', '');

        $this->requester = User::factory()->create(['role' => 'user']);
        $this->volunteer = User::factory()->create(['role' => 'volunteer', 'role_verified_at' => now()]);
    }

    // ─── Cash Flow ──────────────────────────────────────────

    public function test_cash_flow_full_lifecycle(): void
    {
        // 1. User creates help request with cash payment
        Sanctum::actingAs($this->requester);
        $createResponse = $this->postJson('/api/help-requests', [
            'urgency' => 'medium',
            'assistance_type' => 'navigation',
            'details' => 'Need help getting to mall entrance',
            'payment_method' => 'cash',
            'service_fee' => 50,
            'from_label' => 'Home',
            'from_lat' => 30.0444,
            'from_lng' => 31.2357,
            'to_label' => 'Mall of Egypt',
            'to_lat' => 30.0500,
            'to_lng' => 31.2400,
        ]);

        $createResponse->assertCreated()->assertJsonPath('success', true);
        $helpRequestId = $createResponse->json('data.id');

        $this->assertDatabaseHas('help_requests', [
            'id' => $helpRequestId,
            'payment_method' => 'cash',
            'service_fee' => 5000,
            'status' => 'pending',
        ]);

        // 2. Volunteer accepts → status goes to 'active' (cash = no payment needed)
        Sanctum::actingAs($this->volunteer);
        $acceptResponse = $this->postJson("/api/help-requests/{$helpRequestId}/accept");

        $acceptResponse->assertOk()->assertJsonPath('success', true);
        $acceptResponse->assertJsonPath('data.status', 'active');
        $acceptResponse->assertJsonPath('data.payment_method', 'cash');

        // Notification created for requester
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->requester->id,
            'type' => 'volunteer_accepted',
        ]);

        // 3. Volunteer completes service
        $completeResponse = $this->postJson("/api/help-requests/{$helpRequestId}/complete");

        $completeResponse->assertOk()->assertJsonPath('success', true);
        $completeResponse->assertJsonPath('data.status', 'completed');

        // Rating notification created
        $this->assertDatabaseHas('notifications', [
            'user_id' => $this->requester->id,
            'type' => 'service_completed',
        ]);

        $this->assertDatabaseHas('help_requests', [
            'id' => $helpRequestId,
            'status' => 'completed',
        ]);
    }

    // ─── Card Flow ──────────────────────────────────────────

    public function test_card_flow_accept_sets_pending_payment(): void
    {
        Sanctum::actingAs($this->requester);
        $createResponse = $this->postJson('/api/help-requests', [
            'urgency' => 'high',
            'assistance_type' => 'finding_location',
            'details' => 'Finding accessible entrance',
            'payment_method' => 'card',
            'service_fee' => 100,
            'from_label' => 'Home',
            'from_lat' => 30.0444,
            'from_lng' => 31.2357,
            'to_label' => 'Mall of Egypt',
            'to_lat' => 30.0500,
            'to_lng' => 31.2400,
        ]);

        $createResponse->assertCreated();
        $helpRequestId = $createResponse->json('data.id');

        $this->assertDatabaseHas('help_requests', [
            'id' => $helpRequestId,
            'payment_method' => 'card',
            'service_fee' => 10000,
        ]);

        // Volunteer accepts → status = pending_payment
        Sanctum::actingAs($this->volunteer);
        $acceptResponse = $this->postJson("/api/help-requests/{$helpRequestId}/accept");

        $acceptResponse->assertOk();
        $acceptResponse->assertJsonPath('data.status', 'pending_payment');
        $acceptResponse->assertJsonPath('data.payment_method', 'card');

        // Notification with requires_payment = true
        $notification = Notification::where('user_id', $this->requester->id)
            ->where('type', 'volunteer_accepted')
            ->first();

        $this->assertNotNull($notification);
        $this->assertTrue($notification->metadata['requires_payment']);
        $this->assertEquals('Please complete payment to confirm your booking.', $notification->body);
    }

    public function test_card_flow_pay_for_service_creates_checkout(): void
    {
        // Setup: create help request and have volunteer accept it
        $helpRequest = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'urgency_level' => 'high',
            'assistance_type' => 'finding_location',
            'payment_method' => 'card',
            'service_fee' => 10000,
            'from_name' => 'Home',
            'from_lat' => 30.0444,
            'from_lng' => 31.2357,
            'to_name' => 'Mall',
            'to_lat' => 30.05,
            'to_lng' => 31.24,
            'status' => 'pending_payment',
            'accepted_at' => now(),
        ]);

        Http::fake([
            'https://accept.paymob.com/api/auth/tokens' => Http::response(['token' => 'auth_tok'], 200),
            'https://accept.paymob.com/api/ecommerce/orders' => Http::response(['id' => 777888], 200),
            'https://accept.paymob.com/api/acceptance/payment_keys' => Http::response(['token' => 'pay_tok'], 200),
        ]);

        Sanctum::actingAs($this->requester);
        $payResponse = $this->postJson("/api/help-requests/{$helpRequest->id}/pay", [
            'first_name' => 'Khaled',
            'last_name' => 'Ahmed',
            'email' => 'khaled@example.com',
            'phone_number' => '01012345678',
        ]);

        $payResponse->assertOk()->assertJsonPath('success', true);
        $payResponse->assertJsonPath('data.help_request_id', $helpRequest->id);
        $payResponse->assertJsonPath('data.payment_method', 'card');
        $payResponse->assertJsonPath('data.amount_cents', 10000);
        $payResponse->assertJsonPath('data.amount_egp', 100);
        $payResponse->assertJsonPath('data.paymob_order_id', '777888');

        $this->assertNotNull($payResponse->json('data.checkout_url'));

        // Payment record created and linked
        $this->assertDatabaseHas('payments', [
            'help_request_id' => $helpRequest->id,
            'user_id' => $this->requester->id,
            'amount_cents' => 10000,
            'paymob_order_id' => '777888',
            'status' => 'initiated',
        ]);
    }

    public function test_card_flow_callback_confirms_help_request(): void
    {
        // Setup: help request in pending_payment with a linked payment
        $helpRequest = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'urgency_level' => 'high',
            'assistance_type' => 'navigation',
            'payment_method' => 'card',
            'service_fee' => 10000,
            'from_name' => 'A',
            'from_lat' => 30.04,
            'from_lng' => 31.23,
            'to_name' => 'B',
            'status' => 'pending_payment',
            'accepted_at' => now(),
        ]);

        $payment = Payment::create([
            'help_request_id' => $helpRequest->id,
            'user_id' => $this->requester->id,
            'payment_method' => 'card',
            'amount_cents' => 10000,
            'total_amount' => 100,
            'currency' => 'EGP',
            'order_reference' => 'ATHAR-TESTCB-001',
            'paymob_order_id' => '999888',
            'status' => 'initiated',
            'success' => false,
        ]);

        // Simulate Paymob success callback
        $callbackPayload = [
            'obj' => [
                'id' => 112233,
                'success' => true,
                'order' => [
                    'id' => 999888,
                    'merchant_order_id' => 'ATHAR-TESTCB-001',
                ],
            ],
        ];

        $this->postJson('/api/payments/paymob/callback', $callbackPayload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'paid');

        // Payment marked as paid
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'paid',
            'success' => true,
        ]);

        // Help request auto-confirmed to active
        $this->assertDatabaseHas('help_requests', [
            'id' => $helpRequest->id,
            'status' => 'active',
        ]);
    }

    public function test_card_flow_complete_after_payment_triggers_rating_notification(): void
    {
        // Setup: help request already active (payment done)
        $helpRequest = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'urgency_level' => 'high',
            'assistance_type' => 'navigation',
            'payment_method' => 'card',
            'service_fee' => 10000,
            'from_name' => 'A',
            'from_lat' => 30.04,
            'from_lng' => 31.23,
            'to_name' => 'B',
            'status' => 'active',
            'accepted_at' => now(),
        ]);

        Sanctum::actingAs($this->volunteer);
        $completeResponse = $this->postJson("/api/help-requests/{$helpRequest->id}/complete");

        $completeResponse->assertOk();
        $completeResponse->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('help_requests', [
            'id' => $helpRequest->id,
            'status' => 'completed',
        ]);

        // Rating notification
        $notification = Notification::where('user_id', $this->requester->id)
            ->where('type', 'service_completed')
            ->first();

        $this->assertNotNull($notification);
        $this->assertEquals('rate_experience', $notification->metadata['action']);
    }

    // ─── Edge cases ─────────────────────────────────────────

    public function test_pay_rejects_non_pending_payment_request(): void
    {
        $helpRequest = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'urgency_level' => 'low',
            'assistance_type' => 'other',
            'payment_method' => 'card',
            'service_fee' => 5000,
            'from_name' => 'A',
            'from_lat' => 30.04,
            'from_lng' => 31.23,
            'to_name' => 'B',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->requester);
        $this->postJson("/api/help-requests/{$helpRequest->id}/pay", [
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com',
            'phone_number' => '01000000000',
        ])->assertStatus(422);
    }

    public function test_failed_callback_does_not_confirm_help_request(): void
    {
        $helpRequest = HelpRequest::create([
            'requester_id' => $this->requester->id,
            'user_id' => $this->requester->id,
            'volunteer_id' => $this->volunteer->id,
            'urgency_level' => 'high',
            'assistance_type' => 'navigation',
            'payment_method' => 'card',
            'service_fee' => 10000,
            'from_name' => 'A',
            'from_lat' => 30.04,
            'from_lng' => 31.23,
            'to_name' => 'B',
            'status' => 'pending_payment',
            'accepted_at' => now(),
        ]);

        $payment = Payment::create([
            'help_request_id' => $helpRequest->id,
            'user_id' => $this->requester->id,
            'payment_method' => 'card',
            'amount_cents' => 10000,
            'total_amount' => 100,
            'currency' => 'EGP',
            'order_reference' => 'ATHAR-FAIL-001',
            'paymob_order_id' => '555666',
            'status' => 'initiated',
            'success' => false,
        ]);

        $callbackPayload = [
            'obj' => [
                'id' => 445566,
                'success' => false,
                'order' => [
                    'id' => 555666,
                    'merchant_order_id' => 'ATHAR-FAIL-001',
                ],
            ],
        ];

        $this->postJson('/api/payments/paymob/callback', $callbackPayload)
            ->assertOk();

        // Payment failed
        $this->assertDatabaseHas('payments', [
            'id' => $payment->id,
            'status' => 'failed',
            'success' => false,
        ]);

        // Help request stays pending_payment
        $this->assertDatabaseHas('help_requests', [
            'id' => $helpRequest->id,
            'status' => 'pending_payment',
        ]);
    }

    public function test_store_requires_payment_method(): void
    {
        Sanctum::actingAs($this->requester);

        $this->postJson('/api/help-requests', [
            'urgency' => 'medium',
            'assistance_type' => 'navigation',
            'from_label' => 'Home',
            'from_lat' => 30.0444,
            'from_lng' => 31.2357,
            'to_label' => 'Mall',
            'to_lat' => 30.05,
            'to_lng' => 31.24,
            // missing payment_method and service_fee
        ])->assertStatus(422);
    }
}
